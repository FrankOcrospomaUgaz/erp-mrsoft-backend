<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Mensaje
 * 
 * @property int $id
 * @property string $nombre
 * @property string $detalle
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Mensaje extends Model
{
	use SoftDeletes;
	protected $table = 'mensajes';

	protected $fillable = [
		'nombre',
		'detalle'
	];
}
