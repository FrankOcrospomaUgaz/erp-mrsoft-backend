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
 * Class TiposUsuario
 * 
 * @property int $id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class TiposUsuario extends Model
{
	use SoftDeletes;
	protected $table = 'tipos_usuario';

	protected $fillable = [
		'nombre'
	];

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'tipo_usuario_id');
	}
}
