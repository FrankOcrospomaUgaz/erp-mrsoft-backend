<?php

namespace App\Services\Facturacion;

use App\Models\Cliente;
use App\Models\Comprobante;
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
            $cliente = Cliente::with('contactos_clientes')->findOrFail($data['cliente_id']);
            $tipoDocumento = $data['tipo_documento'] ?? 'F';
            $serie = strtoupper($data['serie'] ?? ($tipoDocumento === 'F' ? 'F001' : 'B001'));
            $facturador = $this->resolveFacturador($data['facturador_id'] ?? null);
            $detalles = $this->normalizarDetalles($data['detalles'] ?? []);
            $this->validarCliente($cliente, $tipoDocumento);

            $correlativo = $data['correlativo'] ?? $this->siguienteCorrelativo($tipoDocumento, $serie);
            $totales = $this->calcularTotales($detalles, (float) $facturador->porcentaje_igv);
            $fecha = $data['fecha_emision'] ?? now()->toDateString();

            $comprobante = Comprobante::create([
                'cliente_id' => $cliente->id,
                'contrato_id' => $data['contrato_id'] ?? null,
                'cuota_id' => $data['cuota_id'] ?? null,
                'facturador_id' => $facturador->id,
                'tipo_documento' => $tipoDocumento,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'moneda' => $data['moneda'] ?? 'PEN',
                'forma_pago' => $data['forma_pago'] ?? 'C',
                'fecha_emision' => $fecha,
                'hora_emision' => Carbon::now()->format('H:i:s'),
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
            $comprobante = Comprobante::with(['cliente.contactos_clientes', 'detalles', 'facturador'])
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if (in_array($comprobante->estado, ['M', 'T'], true)) {
                return $comprobante;
            }

            $payload = $comprobante->payload ?: $this->armarPayload($comprobante, $comprobante->facturador);
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
                'error_code' => $aceptado ? null : (string) ($respuesta['code'] ?? 'ERROR'),
                'error_text' => $aceptado ? null : ($respuesta['mensaje'] ?? 'Error al emitir comprobante.'),
                'fecha_respuesta' => now(),
            ]);

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
                $resultados[] = [
                    'cliente_id' => $clienteId,
                    'ok' => true,
                    'comprobante' => $this->crear($payload, (bool) ($data['emitir'] ?? true)),
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
            ->max('correlativo');

        return ((int) $ultimo) + 1;
    }

    private function validarCliente(Cliente $cliente, string $tipoDocumento): void
    {
        if ($tipoDocumento === 'F' && !preg_match('/^\d{11}$/', (string) $cliente->ruc)) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Para emitir factura el cliente debe tener RUC de 11 digitos.',
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
            $lineSubtotal = round($detalle['cantidad'] * $detalle['precio_unitario'], 2);
            $lineIgv = $detalle['tipo_igv'] === '10' ? round($lineSubtotal * ($porcentajeIgv / 100), 2) : 0;
            $lineTotal = round($lineSubtotal + $lineIgv, 2);
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
            'total' => round($subtotal + $igv, 2),
            'detalles' => $calculados,
        ];
    }

    private function armarPayload(Comprobante $comprobante, Facturador $facturador): array
    {
        $cliente = $comprobante->cliente;
        $contacto = $cliente->contactos_clientes->first();

        return [
            'tipo_documento' => $comprobante->tipo_documento,
            'serie' => $comprobante->serie,
            'correlativo' => $comprobante->correlativo,
            'numeroboleta' => $comprobante->serie . '-' . str_pad((string) $comprobante->correlativo, 6, '0', STR_PAD_LEFT),
            'fechaemision' => optional($comprobante->fecha_emision)->format('Y-m-d'),
            'horaemision' => $comprobante->hora_emision,
            'moneda' => $comprobante->moneda,
            'formapago' => $comprobante->forma_pago,
            'porcentajeigv' => (float) $facturador->porcentaje_igv,
            'cliente' => [
                'tipo_doc' => $comprobante->tipo_documento === 'F' ? '6' : ($contacto?->dni ? '1' : '0'),
                'numero_doc' => $cliente->ruc ?: $contacto?->dni,
                'nombre' => $cliente->razon_social ?: $cliente->nombre_comercial ?: $contacto?->nombre,
                'direccion' => $cliente->direccion,
            ],
            'detalles' => $comprobante->detalles->map(fn ($detalle) => [
                'tipodetalle' => 'V',
                'codigo' => (string) ($detalle->producto_id ?: '-'),
                'unidadmedida' => $detalle->unidad,
                'cantidad' => (float) $detalle->cantidad,
                'descripcion' => $detalle->descripcion,
                'precioventaunitarioxitem' => (float) $detalle->precio_unitario,
                'descuentoxitem' => '0',
                'tipoigv' => $detalle->tipo_igv,
                'subtotal' => (float) $detalle->subtotal,
                'igv' => (float) $detalle->igv,
                'total' => (float) $detalle->total,
            ])->values()->all(),
            'subtotal' => (float) $comprobante->subtotal,
            'igv' => (float) $comprobante->igv,
            'total' => (float) $comprobante->total,
        ];
    }

    private function guardarArchivos(Comprobante $comprobante, array $payload, array $respuesta): array
    {
        $base = 'facturacion/' . $comprobante->serie . '-' . str_pad((string) $comprobante->correlativo, 6, '0', STR_PAD_LEFT);
        $xmlPath = $base . '/request.json';
        $cdrPath = $base . '/response.json';

        Storage::disk('local')->put($xmlPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::disk('local')->put($cdrPath, json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (!empty($respuesta['fileZIPBASE64'])) {
            Storage::disk('local')->put($base . '/sunat.zip', base64_decode($respuesta['fileZIPBASE64']));
        }

        return compact('xmlPath', 'cdrPath');
    }
}
