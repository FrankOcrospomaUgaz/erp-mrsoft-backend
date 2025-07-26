<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ContratoProductoModulo
 * 
 * @property int $id
 * @property int $contrato_id
 * @property int $producto_id
 * @property int $modulo_id
 * @property float $precio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Contrato $contrato
 * @property Producto $producto
 * @property Modulo $modulo
 *
 * @package App\Models
 */
class ContratoProductoModulo extends Model
{
	use SoftDeletes;
	protected $table = 'contrato_producto_modulo';

	protected $casts = [
		'contrato_id' => 'int',
		'producto_id' => 'int',
		'modulo_id' => 'int',
		'precio' => 'float'
	];

	protected $fillable = [
		'contrato_id',
		'producto_id',
		'modulo_id',
		'precio'
	];

	public function contrato()
	{
		return $this->belongsTo(Contrato::class);
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}

	public function modulo()
	{
		return $this->belongsTo(Modulo::class);
	}
}
