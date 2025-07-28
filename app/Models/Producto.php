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
 * Class Producto
 * 
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Modulo[] $modulos
 * @property Collection|Contrato[] $contratos
 * @property Collection|AvisosSaa[] $avisos_saas
 *
 * @package App\Models
 */
class Producto extends Model
{
	use SoftDeletes;
	protected $table = 'productos';

	protected $fillable = [
		'nombre',
		'descripcion'
	];

public function modulos()
{
    return $this->hasMany(Modulo::class); // Modulo debe tener producto_id
}


	public function contratos()
	{
		return $this->belongsToMany(Contrato::class, 'contrato_producto_modulo')
					->withPivot('id', 'modulo_id', 'precio', 'deleted_at')
					->withTimestamps();
	}

	public function avisos_saas()
	{
		return $this->hasMany(AvisosSaa::class);
	}
}
