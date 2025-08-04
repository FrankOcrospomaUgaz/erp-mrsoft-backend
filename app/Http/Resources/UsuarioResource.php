<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
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
        'nombres' => $this->nombres,
        'apellidos' => $this->apellidos,
        'usuario' => $this->usuario,
        'tipo_usuario_id' => $this->tipo_usuario_id,
        'tipos_usuario' => $this->tipos_usuario,
    ];
}

}
