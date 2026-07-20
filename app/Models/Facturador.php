<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facturador extends Model
{
    use SoftDeletes;

    protected $table = 'facturadores';

    protected $fillable = [
        'empresa_id',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'usuario_sol',
        'clave_sol',
        'token',
        'wsdl_factura',
        'wsdl_boleta',
        'wsdl_consulta',
        'wsdl_bajas',
        'modo',
        'porcentaje_igv',
        'activo',
    ];

    protected $casts = [
        'porcentaje_igv' => 'decimal:2',
        'activo' => 'bool',
    ];

    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class);
    }
}
