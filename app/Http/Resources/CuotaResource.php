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
        // Calcular monto pagado (usando relación cargada o consulta directa)
        $montoPagado = $this->whenLoaded('pagos_cuota')
            ? $this->pagos_cuota->sum('monto_pagado')
            : $this->pagos_cuota()->sum('monto_pagado');

        // Calcular monto pendiente
        $montoPendiente = max(0, $this->monto - $montoPagado);

        return [
            'id' => $this->id,
            'contrato_id' => $this->contrato_id,
            'monto_total' => $this->monto,
            'monto_pagado' => round($montoPagado, 2),
            'monto_pendiente' => round($montoPendiente, 2),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'situacion' => $this->situacion,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relaciones
            'contrato' => $this->whenLoaded('contrato'),
            'pagos_cuota' => $this->whenLoaded('pagos_cuota'),
        ];
    }
}
