<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class Usuario
 * 
 * @property int $id
 * @property string $nombres
 * @property string $apellidos
 * @property string $usuario
 * @property string $password
 * @property int $tipo_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property TiposUsuario $tipos_usuario
 *
 * @package App\Models
 */
class Usuario extends Model
{
    use HasApiTokens, SoftDeletes;
	protected $table = 'usuarios';

	protected $casts = [
		'tipo_usuario_id' => 'int'
	];

	protected $hidden = [
		'password'
	];

	protected $fillable = [
		'nombres',
		'apellidos',
		'usuario',
		'password',
		'tipo_usuario_id'
	];

	public function tipos_usuario()
	{
		return $this->belongsTo(TiposUsuario::class, 'tipo_usuario_id');
	}
}
