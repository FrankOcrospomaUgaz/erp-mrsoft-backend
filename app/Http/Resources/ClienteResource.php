<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'dueno_nombre' => $this->dueno_nombre,
            'dueno_celular' => $this->dueno_celular,
            'dueno_email' => $this->dueno_email,
            'representante_nombre' => $this->representante_nombre,
            'representante_celular' => $this->representante_celular,
            'representante_email' => $this->representante_email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'contactos_clientes' => $this->contactos_clientes,
            'contratos' => $this->contratos,
            'sucursales_clientes' => $this->sucursales_clientes,
            'notificaciones' => $this->notificaciones,
            'avisos_saas' => $this->avisos_saas,
        ];
    }
}
