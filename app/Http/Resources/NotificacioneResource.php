<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificacioneResource extends JsonResource
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
            'detalle' => $this->detalle,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'cliente' => $this->whenLoaded('cliente'),
        ];
    }
}
