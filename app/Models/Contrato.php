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
        'total',
        'forma_pago'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'numero' => 'string',
        'tipo_contrato' => 'string',
        'total' => 'decimal:2',
        'forma_pago' => 'string'
    ];

    public static array $rules = [
        'fecha_inicio' => 'required',
        'fecha_fin' => 'required',
        'numero' => 'required|string|max:255',
        'cliente_id' => 'required',
        'tipo_contrato' => 'required|string|max:255',
        'total' => 'required|numeric',
        'forma_pago' => 'required|string|max:255',
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
}
