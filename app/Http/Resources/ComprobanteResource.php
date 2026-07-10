<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'contrato_id' => $this->contrato_id,
            'cuota_id' => $this->cuota_id,
            'facturador_id' => $this->facturador_id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero' => $this->serie . '-' . str_pad((string) $this->correlativo, 6, '0', STR_PAD_LEFT),
            'moneda' => $this->moneda,
            'forma_pago' => $this->forma_pago,
            'fecha_emision' => optional($this->fecha_emision)->format('Y-m-d'),
            'hora_emision' => $this->hora_emision,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
            'estado' => $this->estado,
            'estado_label' => $this->estadoLabel(),
            'solicitud_facturador_id' => $this->solicitud_facturador_id,
            'nombre_documento' => $this->nombre_documento,
            'xml_path' => $this->xml_path,
            'cdr_path' => $this->cdr_path,
            'pdf_path' => $this->pdf_path,
            'error_code' => $this->error_code,
            'error_text' => $this->error_text,
            'fecha_envio' => $this->fecha_envio,
            'fecha_respuesta' => $this->fecha_respuesta,
            'detalles' => ComprobanteDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at,
        ];
    }

    private function estadoLabel(): string
    {
        return match ($this->estado) {
            'E' => 'Pendiente',
            'R' => 'Enviado',
            'M', 'T' => 'Aceptado',
            'I', 'V' => 'Rechazado',
            'U' => 'Baja aceptada',
            'X' => 'Error',
            default => 'Desconocido',
        };
    }
}
