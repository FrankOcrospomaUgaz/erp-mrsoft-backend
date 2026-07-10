<?php

namespace App\Services\Facturacion;

use App\Models\Comprobante;
use App\Models\Facturador;
use Illuminate\Support\Str;

class SunatClient
{
    public function enviar(Comprobante $comprobante, Facturador $facturador, array $payload): array
    {
        if ($facturador->modo !== 'produccion') {
            return $this->simularRespuesta($comprobante);
        }

        if (!$facturador->usuario_sol || !$facturador->clave_sol || !$facturador->token) {
            return [
                'ok' => false,
                'code' => 'CONFIG',
                'mensaje' => 'Facturador en produccion sin credenciales completas.',
            ];
        }

        return [
            'ok' => false,
            'code' => 'PENDIENTE_WS',
            'mensaje' => 'Cliente SOAP real pendiente de homologacion y mapeo del proveedor.',
        ];
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
