<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagosCuotumResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cuota_id' => $this->cuota_id,
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'monto_pagado' => $this->monto_pagado,
            'comprobante' => $this->comprobante,
            'metodo_pago' => $this->metodo_pago,
            'estado_revision' => $this->estado_revision,
            'motivo_rechazo' => $this->motivo_rechazo,
            'revisado_por' => $this->revisado_por,
            'revisado_at' => $this->revisado_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'cuota' => $this->whenLoaded('cuota'),
        ];
    }
}
