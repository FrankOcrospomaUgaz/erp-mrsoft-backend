<?php

namespace App\Services\Facturacion;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Contrato;
use App\Models\Facturador;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ComprobanteService
{
    public function __construct(private readonly SunatClient $sunatClient)
    {
    }

    public function crear(array $data, bool $emitir = false): Comprobante
    {
        return DB::transaction(function () use ($data, $emitir) {
            $cliente = !empty($data['cliente_id'])
                ? Cliente::with('contactos_clientes')->findOrFail($data['cliente_id'])
                : null;
            $contrato = !empty($data['contrato_id'])
                ? Contrato::with('cliente.parent_cliente.parent_cliente')->findOrFail($data['contrato_id'])
                : null;
            $this->validarPermisoFacturacion($cliente, $contrato);
            $tipoDocumento = $data['tipo_documento'] ?? 'F';
            $serie = strtoupper($data['serie'] ?? ($tipoDocumento === 'F' ? 'F001' : 'B001'));
            $facturador = $this->resolveFacturador($data['facturador_id'] ?? null);
            $detalles = $this->normalizarDetalles($data['detalles'] ?? []);
            $this->validarCliente($cliente, $tipoDocumento, $data['receptor_documento'] ?? null);

            $correlativo = $data['correlativo'] ?? $this->siguienteCorrelativo($tipoDocumento, $serie);
            $totales = $this->calcularTotales($detalles, (float) $facturador->porcentaje_igv);
            $ahoraFiscal = Carbon::now(config('facturacion.timezone', 'America/Lima'));
            $fecha = $data['fecha_emision'] ?? $ahoraFiscal->toDateString();

            $comprobante = Comprobante::create([
                'cliente_id' => $cliente?->id,
                'receptor_documento' => $cliente ? null : preg_replace('/\D/', '', (string) $data['receptor_documento']),
                'receptor_nombre' => $cliente ? null : (trim((string) ($data['receptor_nombre'] ?? '')) ?: 'RECEPTOR ' . $data['receptor_documento']),
                'receptor_direccion' => $cliente ? null : ($data['receptor_direccion'] ?? null),
                'contrato_id' => $data['contrato_id'] ?? null,
                'cuota_id' => $data['cuota_id'] ?? null,
                'facturador_id' => $facturador->id,
                'tipo_documento' => $tipoDocumento,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'moneda' => $data['moneda'] ?? 'PEN',
                'forma_pago' => $data['forma_pago'] ?? 'C',
                'fecha_emision' => $fecha,
                'hora_emision' => $ahoraFiscal->format('H:i:s'),
                'subtotal' => $totales['subtotal'],
                'igv' => $totales['igv'],
                'total' => $totales['total'],
                'estado' => 'E',
            ]);

            foreach ($totales['detalles'] as $detalle) {
                $comprobante->detalles()->create($detalle);
            }

            $payload = $this->armarPayload($comprobante->load(['cliente.contactos_clientes', 'detalles']), $facturador);
            $comprobante->update(['payload' => $payload]);

            if ($emitir) {
                $this->emitir($comprobante);
            }

            return $comprobante->fresh(['cliente.contactos_clientes', 'detalles', 'facturador']);
        });
    }

    public function emitir(Comprobante $comprobante): Comprobante
    {
        return DB::transaction(function () use ($comprobante) {
            $comprobante = Comprobante::with(['cliente.parent_cliente.parent_cliente', 'cliente.contactos_clientes', 'contrato.cliente.parent_cliente.parent_cliente', 'detalles', 'facturador'])
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if (in_array($comprobante->estado, ['M', 'T', 'U'], true)) {
                return $comprobante;
            }

            $this->validarPermisoFacturacion($comprobante->cliente, $comprobante->contrato);

            $hoyFiscal = Carbon::now(config('facturacion.timezone', 'America/Lima'))->startOfDay();
            if ($comprobante->fecha_emision->startOfDay()->greaterThan($hoyFiscal)) {
                throw ValidationException::withMessages([
                    'fecha_emision' => 'La fecha de emision no puede ser futura en horario de Peru.',
                ]);
            }

            // Siempre se reconstruye para que un reintento use el contrato vigente
            // del proveedor y cualquier correccion guardada.
            $payload = $this->armarPayload($comprobante, $comprobante->facturador);
            $comprobante->update([
                'estado' => 'R',
                'payload' => $payload,
                'sunat_request' => $payload,
                'fecha_envio' => now(),
                'error_code' => null,
                'error_text' => null,
            ]);

            $respuesta = $this->sunatClient->enviar($comprobante, $comprobante->facturador, $payload);
            $paths = $this->guardarArchivos($comprobante, $payload, $respuesta);
            $aceptado = (bool) ($respuesta['ok'] ?? false);

            $comprobante->update([
                'estado' => $aceptado ? ($comprobante->tipo_documento === 'F' ? 'M' : 'T') : 'X',
                'sunat_response' => $respuesta,
                'solicitud_facturador_id' => $respuesta['id_solicitud'] ?? null,
                'nombre_documento' => $respuesta['nombre_documento'] ?? null,
                'xml_path' => $paths['xmlPath'],
                'cdr_path' => $paths['cdrPath'],
                'zip_path' => $paths['zipPath'],
                'error_code' => $aceptado ? null : (string) ($respuesta['code'] ?? 'ERROR'),
                'error_text' => $aceptado ? null : ($respuesta['mensaje'] ?? 'Error al emitir comprobante.'),
                'fecha_respuesta' => now(),
            ]);

            return $comprobante->fresh(['cliente.contactos_clientes', 'detalles', 'facturador']);
        });
    }

    public function actualizar(Comprobante $comprobante, array $data): Comprobante
    {
        return DB::transaction(function () use ($comprobante, $data) {
            $comprobante = Comprobante::with(['cliente.contactos_clientes', 'detalles', 'facturador'])
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if ($comprobante->estado === 'U'
                || (in_array($comprobante->estado, ['M', 'T'], true) && $comprobante->tieneCdrAceptado())) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este comprobante ya cuenta con constancia CDR/ZIP aceptada por SUNAT y no se puede modificar. Debes emitir una nota de credito.',
                ]);
            }

            $cliente = Cliente::with('contactos_clientes')->findOrFail($data['cliente_id']);
            $tipoDocumento = $data['tipo_documento'];
            $facturador = $this->resolveFacturador($data['facturador_id'] ?? $comprobante->facturador_id);
            $detalles = $this->normalizarDetalles($data['detalles']);
            $this->validarCliente($cliente, $tipoDocumento);
            $totales = $this->calcularTotales($detalles, (float) $facturador->porcentaje_igv);

            $comprobante->update([
                'cliente_id' => $cliente?->id,
                'receptor_documento' => $cliente ? null : preg_replace('/\D/', '', (string) $data['receptor_documento']),
                'receptor_nombre' => $cliente ? null : (trim((string) ($data['receptor_nombre'] ?? '')) ?: 'RECEPTOR ' . $data['receptor_documento']),
                'receptor_direccion' => $cliente ? null : ($data['receptor_direccion'] ?? null),
                'facturador_id' => $facturador->id,
                'tipo_documento' => $tipoDocumento,
                'serie' => strtoupper($data['serie'] ?? $comprobante->serie),
                'correlativo' => $data['correlativo'] ?? $comprobante->correlativo,
                'moneda' => strtoupper($data['moneda'] ?? $comprobante->moneda),
                'forma_pago' => $data['forma_pago'] ?? $comprobante->forma_pago,
                'fecha_emision' => $data['fecha_emision'] ?? $comprobante->fecha_emision,
                'subtotal' => $totales['subtotal'],
                'igv' => $totales['igv'],
                'total' => $totales['total'],
                'estado' => 'E',
                'payload' => null,
                'sunat_request' => null,
                'sunat_response' => null,
                'solicitud_facturador_id' => null,
                'nombre_documento' => null,
                'xml_path' => null,
                'cdr_path' => null,
                'zip_path' => null,
                'error_code' => null,
                'error_text' => null,
                'fecha_envio' => null,
                'fecha_respuesta' => null,
            ]);

            $comprobante->detalles()->delete();
            foreach ($totales['detalles'] as $detalle) {
                $comprobante->detalles()->create($detalle);
            }

            $comprobante->load(['cliente.contactos_clientes', 'detalles', 'facturador']);
            $payload = $this->armarPayload($comprobante, $facturador);
            $comprobante->update(['payload' => $payload]);

            return $comprobante->fresh(['cliente.contactos_clientes', 'detalles', 'facturador']);
        });
    }

    public function emitirMasivo(array $data): array
    {
        $resultados = [];

        foreach ($data['cliente_ids'] as $clienteId) {
            try {
                $payload = $data;
                $payload['cliente_id'] = $clienteId;
                $comprobante = $this->crear($payload, (bool) ($data['emitir'] ?? true));
                $resultados[] = [
                    'cliente_id' => $clienteId,
                    'ok' => $comprobante->estado !== 'X',
                    'message' => $comprobante->estado === 'X' ? $comprobante->error_text : null,
                    'comprobante' => $comprobante,
                ];
            } catch (\Throwable $e) {
                $resultados[] = [
                    'cliente_id' => $clienteId,
                    'ok' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    private function resolveFacturador(?int $id): Facturador
    {
        if ($id) {
            return Facturador::findOrFail($id);
        }

        $facturador = Facturador::where('activo', true)->first();

        if ($facturador) {
            return $facturador;
        }

        return Facturador::create([
            'empresa_id' => config('facturacion.empresa_id'),
            'ruc' => config('facturacion.ruc'),
            'razon_social' => config('facturacion.razon_social'),
            'nombre_comercial' => config('facturacion.nombre_comercial'),
            'direccion' => config('facturacion.direccion'),
            'usuario_sol' => config('facturacion.usuario_sol'),
            'clave_sol' => config('facturacion.clave_sol'),
            'token' => config('facturacion.token'),
            'wsdl_factura' => config('facturacion.wsdl.factura'),
            'wsdl_boleta' => config('facturacion.wsdl.boleta'),
            'wsdl_consulta' => config('facturacion.wsdl.consulta'),
            'wsdl_bajas' => config('facturacion.wsdl.bajas'),
            'modo' => config('facturacion.modo', 'simulacion'),
            'porcentaje_igv' => config('facturacion.porcentaje_igv', 18),
            'activo' => true,
        ]);
    }

    private function siguienteCorrelativo(string $tipoDocumento, string $serie): int
    {
        $ultimo = Comprobante::where('tipo_documento', $tipoDocumento)
            ->where('serie', $serie)
            ->lockForUpdate()
            ->orderByDesc('correlativo')
            ->value('correlativo');

        return ((int) $ultimo) + 1;
    }

    private function validarCliente(?Cliente $cliente, string $tipoDocumento, ?string $receptorDocumento = null): void
    {
        $rucResolvido = $cliente
            ? ($cliente->ruc ?: $cliente->parent_cliente?->ruc ?: $cliente->parent_cliente?->parent_cliente?->ruc)
            : $receptorDocumento;
        if ($tipoDocumento === 'F' && !preg_match('/^\d{11}$/', (string) $rucResolvido)) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Para emitir factura el receptor debe tener RUC de 11 dígitos.',
            ]);
        }
        if ($tipoDocumento === 'B' && !preg_match('/^\d{8}$|^\d{11}$/', (string) ($cliente ? ($rucResolvido ?: $cliente->contactos_clientes->first()?->dni) : $receptorDocumento))) {
            throw ValidationException::withMessages([
                'receptor_documento' => 'Para emitir boleta registra un DNI de 8 dígitos o RUC de 11 dígitos.',
            ]);
        }
    }

    private function validarPermisoFacturacion(?Cliente $cliente, ?Contrato $contrato = null): void
    {
        if ($contrato && $cliente && (int) $contrato->cliente_id !== (int) $cliente->id) {
            throw ValidationException::withMessages([
                'contrato_id' => 'El contrato seleccionado no pertenece al cliente.',
            ]);
        }

        if (($contrato && $contrato->noDebeFacturarse()) || ($cliente && $cliente->noDebeFacturarse())) {
            throw ValidationException::withMessages([
                'facturacion' => 'Este cliente o contrato está marcado como No facturado. La deuda y los pagos se controlan normalmente, pero no se permite emitir comprobantes.',
            ]);
        }
    }

    private function normalizarDetalles(array $detalles): array
    {
        if (count($detalles) === 0) {
            throw ValidationException::withMessages([
                'detalles' => 'Debe registrar al menos un detalle.',
            ]);
        }

        return array_map(function (array $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 1);
            $precio = (float) ($detalle['precio_unitario'] ?? 0);

            if ($cantidad <= 0 || $precio <= 0) {
                throw ValidationException::withMessages([
                    'detalles' => 'La cantidad y precio unitario deben ser mayores a cero.',
                ]);
            }

            return [
                'producto_id' => $detalle['producto_id'] ?? null,
                'modulo_id' => $detalle['modulo_id'] ?? null,
                'descripcion' => trim((string) ($detalle['descripcion'] ?? 'Servicio')),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'tipo_igv' => $detalle['tipo_igv'] ?? '10',
                'unidad' => $detalle['unidad'] ?? 'NIU',
            ];
        }, $detalles);
    }

    private function calcularTotales(array $detalles, float $porcentajeIgv): array
    {
        $subtotal = 0;
        $igv = 0;
        $calculados = [];

        foreach ($detalles as $detalle) {
            // El precio ingresado ya incluye IGV. Se desglosa sin volver a sumarlo.
            $lineTotal = round($detalle['cantidad'] * $detalle['precio_unitario'], 2);
            $lineSubtotal = $detalle['tipo_igv'] === '10'
                ? round($lineTotal / (1 + ($porcentajeIgv / 100)), 2)
                : $lineTotal;
            $lineIgv = round($lineTotal - $lineSubtotal, 2);
            $subtotal += $lineSubtotal;
            $igv += $lineIgv;
            $calculados[] = $detalle + [
                'subtotal' => $lineSubtotal,
                'igv' => $lineIgv,
                'total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'igv' => round($igv, 2),
            'total' => round(array_sum(array_column($calculados, 'total')), 2),
            'detalles' => $calculados,
        ];
    }

    private function limpiarTexto(?string $texto, int $max = 100): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        // Reemplazar símbolos comunes de direcciones y títulos conflictivos con SQL remoto
        $texto = str_replace(
            ['ª', 'º', '°', '–', '—', '“', '”', '’', '‘', '`'],
            ['a.', 'o.', '.', '-', '-', '"', '"', "'", "'", "'"],
            $texto
        );

        // Eliminar comillas y caracteres de escape para evitar fallos de sintaxis en el facturador
        $texto = str_replace(["'", '"', '\\'], '', $texto);

        // Normalizar espacios en blanco y saltos de línea
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        $texto = trim($texto);

        return mb_substr($texto, 0, $max, 'UTF-8');
    }

    private function armarPayload(Comprobante $comprobante, Facturador $facturador): array
    {
        $cliente = $comprobante->cliente;
        $parent = $cliente?->parent_cliente;
        $grandParent = $parent?->parent_cliente;
        $contacto = $cliente?->contactos_clientes?->first() ?: $parent?->contactos_clientes?->first();

        $rucResolvido = $cliente?->ruc ?: $parent?->ruc ?: $grandParent?->ruc ?: $comprobante->receptor_documento;
        $razonSocialResolvida = $cliente?->razon_social
            ?: ($cliente?->ruc ? $cliente?->nombre_comercial : null)
            ?: $parent?->razon_social
            ?: $grandParent?->razon_social
            ?: $cliente?->nombre_comercial
            ?: $contacto?->nombre;
        $razonSocialResolvida = $razonSocialResolvida ?: $comprobante->receptor_nombre;
        $direccionResolvida = $cliente?->direccion ?: $parent?->direccion ?: $grandParent?->direccion ?: $comprobante->receptor_direccion;

        $razonSocialLimpia = $this->limpiarTexto($razonSocialResolvida, 100);
        $direccionLimpia = $this->limpiarTexto($direccionResolvida, 100);

        $serieNumerica = $this->normalizarSerieFacturador($comprobante->tipo_documento, $comprobante->serie);
        // El facturador agrega F/B según el campo seriefactura/serieboleta.
        // HotelHUB envía también numerofactura/numeroboleta sin ese prefijo.
        $numero = $serieNumerica . '-' . str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT);
        $documentoCliente = preg_replace('/\D/', '', (string) ($rucResolvido ?: $contacto?->dni ?: ''));
        $tipoDocumentoCliente = $comprobante->tipo_documento === 'F'
            ? 6
            : ($rucResolvido ? 6 : ($contacto?->dni ? 1 : 0));

        // Contrato real de comprobante-e: el comprobante va serializado dentro
        // de un sobre con serie, correlativo y datos del receptor.
        $documento = [
            $comprobante->tipo_documento === 'F' ? 'numerofactura' : 'numeroboleta' => $numero,
            'fechaemision' => optional($comprobante->fecha_emision)->format('Y-m-d'),
            'horaemision' => $comprobante->hora_emision,
            'usuario' => $razonSocialLimpia,
            'codubigeo' => '0000',
            'tipodoc' => $tipoDocumentoCliente,
            $comprobante->tipo_documento === 'F' ? 'ruc' : 'dni' => $documentoCliente,
            'moneda' => $comprobante->moneda,
            'descuentototal' => '0',
            'percepcion' => '',
            'aplicacionpercepcion' => '',
            'documentosanexos' => [],
            'detalles' => $comprobante->detalles->values()->map(fn ($detalle) => [
                'tipodetalle' => 'V',
                'codigo' => (string) ($detalle->producto_id ?: '-'),
                'unidadmedida' => $detalle->unidad,
                'cantidad' => (float) $detalle->cantidad,
                'descripcion' => $this->limpiarTexto($detalle->descripcion, 250),
                'precioventaunitarioxitem' => (float) $detalle->precio_unitario,
                'descuentoxitem' => '0',
                'tipoigv' => $detalle->tipo_igv,
                'tasaisc' => '0',
                'aplicacionisc' => '',
                'precioventasugeridoxitem' => '',
            ])->values()->all(),
            'formapago' => $comprobante->forma_pago,
        ];

        $serieKey = $comprobante->tipo_documento === 'F' ? 'seriefactura' : 'serieboleta';
        $correlativoKey = $comprobante->tipo_documento === 'F' ? 'correlativofactura' : 'correlativoboleta';

        return [
            'token' => (string) ($facturador->token ?? ''),
            // El facturador agrega la letra del tipo de documento al representar
            // la serie; enviamos solo los tres dígitos para evitar FF002.
            $serieKey => $serieNumerica,
            $correlativoKey => str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT),
            'doc' => (string) $documentoCliente,
            'nombre' => (string) $razonSocialLimpia,
            'direccion' => (string) $direccionLimpia,
            'total' => (float) $comprobante->total,
            'comprobante' => json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function normalizarSerieFacturador(string $tipoDocumento, string $serie): string
    {
        $prefijo = $tipoDocumento === 'F' ? 'F' : 'B';
        $serie = strtoupper(trim($serie));

        // En el sobre del facturador el tipo define F/B; el campo de serie debe
        // contener únicamente los tres dígitos, incluso si el dato trae prefijos repetidos.
        $serieNumerica = preg_replace('/^' . $prefijo . '+/', '', $serie);
        if (!is_string($serieNumerica) || !preg_match('/^\d{3}$/', $serieNumerica)) {
            throw ValidationException::withMessages([
                'serie' => "La serie para {$tipoDocumento} debe terminar en exactamente tres dígitos y coincidir con su tipo de comprobante.",
            ]);
        }

        return $serieNumerica;
    }

    private function guardarArchivos(Comprobante $comprobante, array $payload, array $respuesta): array
    {
        $base = 'facturacion/' . $comprobante->serie . '-' . str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT);
        $xmlPath = $base . '/request.json';
        $cdrPath = $base . '/response.json';

        Storage::disk('local')->put($xmlPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::disk('local')->put($cdrPath, json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zipPath = null;
        if (!empty($respuesta['fileZIPBASE64'])) {
            $zip = base64_decode((string) $respuesta['fileZIPBASE64'], true);
            if ($zip !== false) {
                $zipPath = $base . '/sunat.zip';
                Storage::disk('local')->put($zipPath, $zip);
            }
        }

        return compact('xmlPath', 'cdrPath', 'zipPath');
    }
}
