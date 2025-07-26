<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SucursalesCliente
 * 
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cliente $cliente
 *
 * @package App\Models
 */
class SucursalesCliente extends Model
{
	use SoftDeletes;
	protected $table = 'sucursales_cliente';

	protected $casts = [
		'cliente_id' => 'int'
	];

	protected $fillable = [
		'cliente_id',
		'nombre'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}
}
