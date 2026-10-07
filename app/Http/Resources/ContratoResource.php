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
            'vigencia_contrato' => $this->vigencia_contrato,
            'duracion_anios' => $this->duracion_anios,
            'costo_instalacion' => (float) ($this->costo_instalacion ?? 0),
            'total'        => $this->total,
            'forma_pago'   => $this->forma_pago,
            'estado'       => $this->estado,
            'no_facturado' => (bool) $this->no_facturado,
            'no_facturado_efectivo' => $this->noDebeFacturarse(),
            'periodicidad_cuota' => $this->periodicidad_cuota,
            'kuti_subscription_id' => $this->kuti_subscription_id,
            'kuti_subscription_status' => $this->kuti_subscription_status,
            'kuti_subscription_checkout_url' => $this->when($this->kuti_subscription_status !== 'ACTIVE', $this->kuti_subscription_checkout_url),
            'kuti_subscription_frequency' => $this->kuti_subscription_frequency,
            'kuti_subscription_amount' => $this->kuti_subscription_amount,
            'kuti_subscription_next_charge_at' => $this->kuti_subscription_next_charge_at?->toIso8601String(),
            'kuti_subscription_charge_time' => $this->kuti_subscription_charge_time,
            'motivo_anulacion' => $this->motivo_anulacion,
            'fecha_anulacion' => $this->fecha_anulacion,
            'has_firma_arrendador' => !empty($this->firma_arrendador),
            'has_firma_cliente' => !empty($this->firma_cliente),
            'firma_arrendador' => $this->when(!$request->routeIs('contratos.index') || $request->boolean('with_firmas'), $this->firma_arrendador),
            'firma_cliente' => $this->when(!$request->routeIs('contratos.index') || $request->boolean('with_firmas'), $this->firma_cliente),
            'created_at'   => $this->created_at ? $this->created_at->toIso8601String() : null,
            'fecha_creacion' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente),
            'cuotas'  => $this->whenLoaded('cuotas', fn () => $this->cuotas),

            'contrato_producto_modulos' => $this->whenLoaded('contratoProductoModulos', function () {
                return $this->contratoProductoModulos->map(function ($cpm) {
                    $producto = $cpm->producto;
                    $modulo = $cpm->modulo;

                    return [
                        'id'          => $cpm->id,
                        'precio'      => $cpm->precio,
                        'producto_id' => $cpm->producto_id,
                        'modulo_id'   => $cpm->modulo_id,

                        'producto' => $producto ? [
                            'id'     => $producto->id,
                            'nombre' => $producto->nombre ?? null,
                            'color'  => $producto->color ?? null,
                            'logo'   => $producto->logo ?? null,
                        ] : null,

                        'modulo' => $modulo ? [
                            'id'              => $modulo->id,
                            'nombre'          => $modulo->nombre ?? null,
                            'precio_unitario' => $modulo->precio_unitario ?? null,
                            'producto_id'     => $modulo->producto_id ?? null,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
