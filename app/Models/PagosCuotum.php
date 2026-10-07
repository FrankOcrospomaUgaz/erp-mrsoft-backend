<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PagosCuotum
 * 
 * @property int $id
 * @property int $cuota_id
 * @property Carbon $fecha_pago
 * @property float $monto_pagado
 * @property string|null $comprobante
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cuota $cuota
 *
 * @package App\Models
 */
class PagosCuotum extends Model
{
	use SoftDeletes;
	protected $table = 'pagos_cuota';

	protected $casts = [
		'cuota_id' => 'int',
		'fecha_pago' => 'datetime',
		'monto_pagado' => 'float'
		,'revisado_at' => 'datetime'
	];

	protected $fillable = [
		'cuota_id',
		'fecha_pago',
		'monto_pagado',
		'comprobante'
		,'metodo_pago'
		,'estado_revision'
		,'motivo_rechazo'
		,'revisado_por'
		,'revisado_at'
	];

	public function cuota()
	{
		return $this->belongsTo(Cuota::class);
	}
}
