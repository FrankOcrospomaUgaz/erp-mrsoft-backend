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
            'parent_cliente_id' => $this->parent_cliente_id,
            'tipo' => $this->tipo,
            'tipo_ui' => $this->tipo === 'unico' ? 'local' : $this->tipo,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion' => $this->direccion,
            'tipos_local' => $this->tipos_local ?? [],
            'nombre_cliente' => $this->razon_social ?? $this->nombre_comercial ?? $this->dueno_nombre,
            'contacto_principal' => $this->contactos_clientes->first() ? [
                'dni' => $this->contactos_clientes->first()->dni,
                'nombre' => $this->contactos_clientes->first()->nombre,
                'celular' => $this->contactos_clientes->first()->celular,
                'email' => $this->contactos_clientes->first()->email,
                'es_dueno' => (bool) $this->contactos_clientes->first()->es_dueno,
                'es_vendedor' => (bool) $this->contactos_clientes->first()->es_vendedor,
            ] : null,
            'dueno_nombre' => $this->dueno_nombre,
            'dueno_celular' => $this->dueno_celular,
            'dueno_email' => $this->dueno_email,
            'dueno_es_representante' => $this->dueno_es_representante,
            'dueno_es_responsable' => $this->dueno_es_responsable,
            'contacto_igual_empresa' => $this->contacto_igual_empresa,
            'representante_nombre' => $this->representante_nombre,
            'representante_celular' => $this->representante_celular,
            'representante_email' => $this->representante_email,
            'responsable_nombre' => $this->responsable_nombre,
            'responsable_celular' => $this->responsable_celular,
            'responsable_email' => $this->responsable_email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'contactos_clientes' => $this->contactos_clientes,
            'contratos' => $this->contratos,
            'sucursales_clientes' => $this->sucursales_clientes,
            'hijos_clientes' => ClienteResource::collection($this->whenLoaded('hijos_clientes')),
            'notificaciones' => $this->notificaciones,
            'avisos_saas' => $this->avisos_saas,
        ];
    }
}
