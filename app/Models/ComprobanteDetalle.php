<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteDetalle extends Model
{
    protected $table = 'comprobante_detalles';

    protected $fillable = [
        'comprobante_id',
        'producto_id',
        'modulo_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'igv',
        'total',
        'tipo_igv',
        'unidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class);
    }
}
