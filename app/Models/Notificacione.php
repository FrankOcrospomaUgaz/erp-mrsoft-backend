<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Notificacione
 * 
 * @property int $id
 * @property int $cliente_id
 * @property string|null $detalle
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cliente $cliente
 *
 * @package App\Models
 */
class Notificacione extends Model
{
	use SoftDeletes;
	protected $table = 'notificaciones';

	protected $casts = [
		'cliente_id' => 'int'
	];

	protected $fillable = [
		'cliente_id',
		'detalle'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}
}
