<?php

namespace App\Services\Facturacion;

use App\Models\Comprobante;
use App\Models\Facturador;
use Illuminate\Support\Str;
use JsonException;
use SoapClient;
use SoapFault;

class SunatClient
{
    public function enviar(Comprobante $comprobante, Facturador $facturador, array $payload): array
    {
        if ($facturador->modo !== 'produccion') {
            return $this->simularRespuesta($comprobante);
        }

        if (!$facturador->empresa_id || !$facturador->usuario_sol || !$facturador->clave_sol) {
            return [
                'ok' => false,
                'code' => 'CONFIG',
                'mensaje' => 'Facturador en produccion sin ID de empresa o credenciales WSDL completas.',
            ];
        }

        $wsdl = $comprobante->tipo_documento === 'B'
            ? $facturador->wsdl_boleta
            : $facturador->wsdl_factura;

        if (!$wsdl) {
            return [
                'ok' => false,
                'code' => 'CONFIG_WSDL',
                'mensaje' => 'No se configuro la URL WSDL para este tipo de comprobante.',
            ];
        }

        try {
            $client = new SoapClient($this->wsdlUrl($wsdl), [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
                'exceptions' => true,
                'trace' => true,
            ]);

            $operation = $comprobante->tipo_documento === 'B' ? 'enviarBoleta' : 'enviarFactura';
            $rucEmisor = (string) ($facturador->ruc ?: $facturador->usuario_sol);
            $claveEmisor = (string) ($facturador->clave_sol ?: $facturador->token);

            // El WSDL es RPC/encoded y recibe tres argumentos posicionales: ruc, password, json
            $raw = $client->__soapCall($operation, [
                $rucEmisor,
                $claveEmisor,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);

            return $this->normalizarRespuesta($raw);
        } catch (SoapFault $e) {
            return [
                'ok' => false,
                'code' => 'SOAP',
                'mensaje' => 'Error SOAP con el servidor de facturación: ' . $e->getMessage(),
                'detalle_error' => $e->getMessage(),
            ];
        } catch (JsonException $e) {
            return [
                'ok' => false,
                'code' => 'JSON',
                'mensaje' => 'No se pudo preparar el comprobante: ' . $e->getMessage(),
                'detalle_error' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'code' => 'CONEXION',
                'mensaje' => 'Error de conexión con el facturador: ' . $e->getMessage(),
                'detalle_error' => $e->getMessage(),
            ];
        }
    }

    private function wsdlUrl(string $url): string
    {
        return str_contains($url, '?') ? $url : $url . '?wsdl';
    }

    private function normalizarRespuesta(mixed $raw): array
    {
        if (is_object($raw) && property_exists($raw, 'return')) {
            $raw = $raw->return;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'ok' => false,
                    'code' => 'RESPUESTA_INVALIDA',
                    'mensaje' => 'El facturador devolvio una respuesta no valida: ' . $raw,
                    'respuesta_original' => $raw,
                ];
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [
                'ok' => false,
                'code' => 'RESPUESTA_VACIA',
                'mensaje' => 'El facturador no devolvio una respuesta valida.',
                'respuesta_original' => $raw,
            ];
        }

        $code = $raw['code'] ?? $raw['codigo'] ?? $raw['cod_sunat'] ?? $raw['codsunat'] ?? null;
        $explicitSuccess = $raw['ok'] ?? $raw['success'] ?? $raw['exito'] ?? null;
        $ok = $explicitSuccess !== null
            ? filter_var($explicitSuccess, FILTER_VALIDATE_BOOLEAN)
            : in_array((string) $code, ['0', '0000'], true);

        $mensajeRaw = $raw['mensaje'] ?? $raw['message'] ?? $raw['descripcion'] ?? null;

        if ($ok) {
            $mensaje = ($mensajeRaw && !str_starts_with((string) $mensajeRaw, 'eyJ'))
                ? (string) $mensajeRaw
                : 'Comprobante emitido y aceptado correctamente por SUNAT/Facturador.';
        } else {
            $partes = [];
            if ($mensajeRaw && !str_starts_with((string) $mensajeRaw, 'eyJ')) {
                $partes[] = (string) $mensajeRaw;
            } else {
                $partes[] = 'El facturador rechazó el comprobante';
            }

            if (!empty($raw['file']) || !empty($raw['line'])) {
                $archivo = isset($raw['file']) ? basename((string) $raw['file']) : '';
                $linea = $raw['line'] ?? '';
                $partes[] = sprintf('(%s%s)', $archivo ? "archivo: {$archivo}" : '', $linea ? " línea {$linea}" : '');
            }

            if ($code !== null && $code !== 0 && $code !== '0') {
                $partes[] = "[Código: {$code}]";
            }

            if ((string) $code === '9' || str_contains((string) $mensajeRaw, 'ERROR EN LA CONSULTA')) {
                $partes[] = '- Nota: Si este comprobante ya fue emitido antes con esta serie y correlativo, el facturador no permite duplicarlo (debes usar el siguiente correlativo o descargar el ZIP generado).';
            }

            $mensaje = implode(' ', array_filter($partes));
        }

        return array_merge($raw, [
            'ok' => $ok,
            'code' => $code ?? ($ok ? 0 : 'ERROR'),
            'mensaje' => $mensaje,
        ]);
    }

    private function simularRespuesta(Comprobante $comprobante): array
    {
        $nombre = sprintf(
            '%s-%s-%s',
            $comprobante->tipo_documento,
            $comprobante->serie,
            str_pad((string) $comprobante->correlativo, 6, '0', STR_PAD_LEFT)
        );

        return [
            'ok' => true,
            'code' => 0,
            'mensaje' => 'Comprobante simulado correctamente. Pendiente homologacion SUNAT para produccion.',
            'id_solicitud' => 'SIM-' . Str::upper(Str::random(10)),
            'nombre_documento' => $nombre,
            'fileZIPBASE64' => null,
        ];
    }
}
