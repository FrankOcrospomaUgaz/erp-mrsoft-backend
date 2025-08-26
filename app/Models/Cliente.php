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
 * Class Cliente
 * 
 * @property int $id
 * @property string $tipo
 * @property string $ruc
 * @property string $razon_social
 * @property string $dueno_nombre
 * @property string $dueno_celular
 * @property string $dueno_email
 * @property string $representante_nombre
 * @property string $representante_celular
 * @property string $representante_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|ContactosCliente[] $contactos_clientes
 * @property Collection|Contrato[] $contratos
 * @property Collection|SucursalesCliente[] $sucursales_clientes
 * @property Collection|AvisosSaa[] $avisos_saas
 *
 * @package App\Models
 */
class Cliente extends Model
{
	use SoftDeletes;
	protected $table = 'clientes';

	protected $fillable = [
		'tipo',
		'ruc',
		'razon_social',
		'dueno_nombre',
		'dueno_celular',
		'dueno_email',
		'representante_nombre',
		'representante_celular',
		'representante_email'
	];

	public function contactos_clientes()
	{
		return $this->hasMany(ContactosCliente::class);
	}

	public function contratos()
	{
		return $this->hasMany(Contrato::class);
	}

	public function sucursales_clientes()
	{
		return $this->hasMany(SucursalesCliente::class);
	}



	public function avisos_saas()
	{
		return $this->hasMany(AvisosSaa::class);
	}
}
