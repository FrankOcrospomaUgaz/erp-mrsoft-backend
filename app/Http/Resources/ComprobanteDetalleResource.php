<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'modulo_id' => $this->modulo_id,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
            'tipo_igv' => $this->tipo_igv,
            'unidad' => $this->unidad,
        ];
    }
}
