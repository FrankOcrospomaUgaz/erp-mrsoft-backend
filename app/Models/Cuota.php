<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Cuota
 * 
 * @property int $id
 * @property int $contrato_id
 * @property float $monto
 * @property Carbon $fecha_vencimiento
 * @property Carbon|null $fecha_pago
 * @property string $situacion   <--- 🔹 Agregar esta línea
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Contrato $contrato
 * @property Collection|PagosCuotum[] $pagos_cuota
 *
 * @package App\Models
 */
class Cuota extends Model
{
	use SoftDeletes;
	protected $table = 'cuotas';

	protected $casts = [
		'contrato_id' => 'int',
		'monto' => 'float',
		'fecha_vencimiento' => 'datetime',
		'fecha_pago' => 'datetime'
	];

	protected $fillable = [
		'contrato_id',
		'monto',
		'fecha_vencimiento',
		'fecha_pago',
		'situacion'
	];

	public function contrato()
	{
		return $this->belongsTo(Contrato::class);
	}

	public function pagos_cuota()
	{
		return $this->hasMany(PagosCuotum::class);
	}
}
