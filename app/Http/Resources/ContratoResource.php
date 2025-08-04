<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoResource extends JsonResource
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
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'numero' => $this->numero,
            'tipo_contrato' => $this->tipo_contrato,
            'total' => $this->total,
            'forma_pago' => $this->forma_pago,

            'cliente' => $this->cliente,
            'cuotas' => $this->cuotas,
            'contrato_producto_modulos' => $this->contratoProductoModulos,
        ];
    }
}
