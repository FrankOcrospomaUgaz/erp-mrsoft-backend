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
 * @property string $nombre
 * @property string $celular
 * @property string $email
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
		'cliente_id' => 'int'
	];

	protected $fillable = [
		'cliente_id',
		'nombre',
		'celular',
		'email'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}
}
