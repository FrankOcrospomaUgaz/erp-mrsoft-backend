<?php

namespace App\Services\Facturacion;

use App\Models\Comprobante;
use App\Models\Facturador;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class FacturadorSyncService
{
    public function __construct(private readonly SunatClient $sunatClient)
    {
    }

    public function sincronizar(string $fechaInicio, string $fechaFin): array
    {
        $resumen = ['consultados' => 0, 'actualizados' => 0, 'cdr_descargados' => 0, 'errores' => []];
        $facturadores = Facturador::query()
            ->whereHas('comprobantes', fn ($query) => $query->whereBetween('fecha_emision', [$fechaInicio, $fechaFin]))
            ->get();

        foreach ($facturadores as $facturador) {
            $respuesta = $this->sunatClient->descargarComprobantes($facturador, $fechaInicio, $fechaFin);
            if (!($respuesta['ok'] ?? false)) {
                $resumen['errores'][] = [
                    'facturador' => $facturador->nombre_comercial ?: $facturador->razon_social,
                    'mensaje' => $respuesta['mensaje'] ?? 'No se pudo consultar el facturador.',
                ];
                continue;
            }

            $items = $respuesta['comprobantes'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }
            if (is_object($items)) {
                $items = (array) $items;
            }
            if (!is_array($items)) {
                continue;
            }

            $locales = Comprobante::with('detalles')
                ->where('facturador_id', $facturador->id)
                ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
                ->get();

            foreach ($items as $item) {
                $item = is_object($item) ? (array) $item : $item;
                if (!is_array($item)) {
                    continue;
                }
                $resumen['consultados']++;
                $comprobante = $this->buscarComprobante($locales, $item);
                if (!$comprobante) {
                    continue;
                }

                $estadoProveedor = strtoupper((string) ($item['estado_solicitud'] ?? ''));
                $estadoLocal = $this->mapearEstado($estadoProveedor, $comprobante->tipo_documento);
                if (!$estadoLocal) {
                    continue;
                }

                $idSolicitud = $item['id_solicitud'] ?? $comprobante->solicitud_facturador_id;
                $fechaEnvio = $this->parsearFecha($item['fechahora_enviosunat'] ?? null);
                $fechaRespuesta = $this->parsearFecha($item['fechahora_respuestasunat'] ?? null);
                $actualizacion = [
                    'estado' => $estadoLocal,
                    'solicitud_facturador_id' => $idSolicitud ? (string) $idSolicitud : null,
                    'nombre_documento' => $item['nombre_solicitud'] ?? $comprobante->nombre_documento,
                    'fecha_envio' => $fechaEnvio ?? $comprobante->fecha_envio,
                    'fecha_respuesta' => $fechaRespuesta ?? $comprobante->fecha_respuesta,
                    'error_code' => $item['error_code'] ?? null,
                    'error_text' => $item['error_text'] ?? null,
                ];

                if (in_array($estadoProveedor, ['C', 'I'], true) && $idSolicitud) {
                    $cdr = $this->sunatClient->descargarCdr($facturador, (string) $idSolicitud);
                    $zipBase64 = $cdr['file_base64'] ?? null;
                    $zipBinary = is_string($zipBase64) ? base64_decode($zipBase64, true) : false;
                    if (($cdr['ok'] ?? false) && is_string($zipBinary)
                        && CdrInspector::contieneCdr($zipBinary, (string) $comprobante->serie, (int) $comprobante->correlativo)) {
                        $path = sprintf(
                            'facturacion/%s-%08d/sunat.zip',
                            $comprobante->serie,
                            $comprobante->correlativo
                        );
                        Storage::disk('local')->put($path, $zipBinary);
                        $actualizacion['zip_path'] = $path;
                        $actualizacion['sunat_response'] = array_merge($comprobante->sunat_response ?? [], [
                            'fileZIPBASE64' => $zipBase64,
                            'sync_facturador' => [
                                'estado_solicitud' => $estadoProveedor,
                                'sincronizado_en' => now()->toIso8601String(),
                            ],
                        ]);
                        $resumen['cdr_descargados']++;
                    }
                }

                $comprobante->update($actualizacion);
                $resumen['actualizados']++;
            }
        }

        return $resumen;
    }

    private function buscarComprobante($locales, array $item): ?Comprobante
    {
        $idSolicitud = (string) ($item['id_solicitud'] ?? '');
        if ($idSolicitud !== '') {
            $match = $locales->first(fn ($local) => (string) $local->solicitud_facturador_id === $idSolicitud);
            if ($match) {
                return $match;
            }
        }

        $data = $item['data_solicitud'] ?? null;
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        if (is_object($data)) {
            $data = (array) $data;
        }
        $data = is_array($data) ? $data : [];
        $referencia = (string) ($item['nombre_solicitud'] ?? $data['numero'] ?? $data['numeracion'] ?? '');
        $serie = (string) ($data['serie'] ?? '');
        $correlativo = $data['correlativo'] ?? $data['numero_correlativo'] ?? null;
        if (!$serie && preg_match('/^([A-Z0-9]+)-(\d+)$/i', $referencia, $parts)) {
            $serie = strtoupper($parts[1]);
            $correlativo = (int) $parts[2];
        }
        if (!$serie || $correlativo === null) {
            return null;
        }

        return $locales->first(fn ($local) => strtoupper($local->serie) === strtoupper($serie)
            && (int) $local->correlativo === (int) $correlativo
            && strtoupper((string) ($item['tipo_documento'] ?? $local->tipo_documento)) === $local->tipo_documento);
    }

    private function mapearEstado(string $estado, string $tipoDocumento): ?string
    {
        return match ($estado) {
            'C' => $tipoDocumento === 'F' ? 'M' : 'T',
            'I' => $tipoDocumento === 'F' ? 'I' : 'V',
            'T', 'E' => 'R',
            default => null,
        };
    }

    private function parsearFecha(?string $fecha): ?Carbon
    {
        if (!$fecha) {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            return null;
        }
    }
}
