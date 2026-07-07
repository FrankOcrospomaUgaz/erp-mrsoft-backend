<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin'    => $this->fecha_fin,
            'numero'       => $this->numero,
            'tipo_contrato'=> $this->tipo_contrato,
            'total'        => $this->total,
            'forma_pago'   => $this->forma_pago,

            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente),
            'cuotas'  => $this->whenLoaded('cuotas', fn () => $this->cuotas),

            'contrato_producto_modulos' => $this->whenLoaded('contratoProductoModulos', function () {
                return $this->contratoProductoModulos->map(function ($cpm) {
                    return [
                        'id'          => $cpm->id,
                        'precio'      => $cpm->precio,
                        'producto_id' => $cpm->producto_id,
                        'modulo_id'   => $cpm->modulo_id,

                        // Datos del producto (si lo cargaste)
                        'producto' => $cpm->relationLoaded('producto') ? [
                            'id'     => $cpm->producto->id,
                            'nombre' => $cpm->producto->nombre ?? null,
                        ] : null,

                        // Datos del módulo (lo que te piden)
                        'modulo' => $cpm->relationLoaded('modulo') ? [
                            'id'              => $cpm->modulo->id,
                            'nombre'          => $cpm->modulo->nombre,
                            'precio_unitario' => $cpm->modulo->precio_unitario,
                            'producto_id'     => $cpm->modulo->producto_id,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
