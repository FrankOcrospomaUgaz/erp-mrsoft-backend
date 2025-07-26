<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AvisosSaa
 * 
 * @property int $id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property int $cliente_id
 * @property int $producto_id
 * @property string $texto_aviso
 * @property string $tipo_aviso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cliente $cliente
 * @property Producto $producto
 *
 * @package App\Models
 */
class AvisosSaa extends Model
{
	use SoftDeletes;
	protected $table = 'avisos_saas';

	protected $casts = [
		'fecha_inicio' => 'datetime',
		'fecha_fin' => 'datetime',
		'cliente_id' => 'int',
		'producto_id' => 'int'
	];

	protected $fillable = [
		'fecha_inicio',
		'fecha_fin',
		'cliente_id',
		'producto_id',
		'texto_aviso',
		'tipo_aviso'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}
}
