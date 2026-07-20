<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ContactosCliente
 * 
 * @property int $id
 * @property int $cliente_id
 * @property string|null $dni
 * @property string $nombre
 * @property string|null $celular
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Cliente $cliente
 *
 * @package App\Models
 */
class ContactosCliente extends Model
{
	use SoftDeletes;
	protected $table = 'contactos_cliente';

	protected $casts = [
		'cliente_id' => 'int',
		'es_dueno' => 'boolean',
		'es_vendedor' => 'boolean'
	];

	protected $fillable = [
		'cliente_id',
		'dni',
		'nombre',
		'celular',
		'email',
		'es_dueno',
		'es_vendedor'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}
}
