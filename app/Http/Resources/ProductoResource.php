<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo ?? 'servicio',
            'descripcion' => $this->descripcion,
            'modulos' => $this->modulos->map(fn($modulo) => [
                'id' => $modulo->id,
                'nombre' => $modulo->nombre,
                'precio_unitario' => $modulo->precio_unitario,
                'precio_mensual' => $modulo->precio_mensual,
                'precio_anual' => $modulo->precio_anual,
                'producto_id' => $modulo->producto_id,
                'created_at' => $modulo->created_at,
                'updated_at' => $modulo->updated_at,
                'deleted_at' => $modulo->deleted_at,
                'contratos' => $modulo->contratos ?? [],
            ]),
            'avisos_saas' => $this->avisos_saas,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
