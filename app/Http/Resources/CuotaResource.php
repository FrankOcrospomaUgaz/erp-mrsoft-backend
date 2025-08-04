<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuotaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contrato_id' => $this->contrato_id,
            'monto' => $this->monto,
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'situacion' => $this->situacion,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'contrato' => $this->whenLoaded('contrato'),
            'pagos_cuota' => $this->whenLoaded('pagos_cuota'),
        ];
    }
}
