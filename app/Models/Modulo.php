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
 * Class Modulo
 * 
 * @property int $id
 * @property string $nombre
 * @property float $precio_unitario
 * @property int $producto_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Producto $producto
 * @property Collection|Contrato[] $contratos
 * @property Collection|Producto[] $productos
 *
 * @package App\Models
 */
class Modulo extends Model
{
	use SoftDeletes;
	protected $table = 'modulos';

	protected $casts = [
		'precio_unitario' => 'float',
		'producto_id' => 'int'
	];

	protected $fillable = [
		'nombre',
		'precio_unitario',
		'producto_id'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class);
	}

	public function contratos()
	{
		return $this->belongsToMany(Contrato::class, 'contrato_producto_modulo')
					->withPivot('id', 'producto_id', 'precio', 'deleted_at')
					->withTimestamps();
	}

	public function productos()
	{
		return $this->belongsToMany(Producto::class, 'contrato_producto_modulo')
					->withPivot('id', 'contrato_id', 'precio', 'deleted_at')
					->withTimestamps();
	}
}
