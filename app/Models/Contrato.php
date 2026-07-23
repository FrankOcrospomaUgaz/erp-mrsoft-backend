<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Contrato extends Model
{
    public $table = 'contratos';
    use SoftDeletes;
    public $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'numero',
        'cliente_id',
        'tipo_contrato',
        'vigencia_contrato',
        'duracion_anios',
        'total',
        'forma_pago',
        'estado',
        'periodicidad_cuota',
        'motivo_anulacion',
        'fecha_anulacion',
        'firma_arrendador',
        'firma_cliente',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'numero' => 'string',
        'tipo_contrato' => 'string',
        'vigencia_contrato' => 'string',
        'duracion_anios' => 'int',
        'total' => 'decimal:2',
        'forma_pago' => 'string',
        'estado' => 'string',
        'periodicidad_cuota' => 'string',
        'fecha_anulacion' => 'date',
    ];

    public static array $rules = [
        'fecha_inicio' => 'required',
        'fecha_fin' => 'required',
        'numero' => 'required|string|max:255',
        'cliente_id' => 'required',
        'tipo_contrato' => 'required|string|max:255',
        'vigencia_contrato' => 'nullable|string|max:255',
        'duracion_anios' => 'nullable|integer|min:1',
        'total' => 'required|numeric',
        'forma_pago' => 'required|string|max:255',
        'estado' => 'nullable|string|max:255',
        'periodicidad_cuota' => 'nullable|string|max:255',
        'motivo_anulacion' => 'nullable|string',
        'fecha_anulacion' => 'nullable|date',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function cliente(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Cliente::class, 'cliente_id');
    }

    public function cuotas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Cuota::class, 'contrato_id');
    }

    public function contratoProductoModulos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ContratoProductoModulo::class, 'contrato_id');
    }

    	public function notificaciones()
	{
		return $this->hasMany(Notificacione::class);
	}
}
