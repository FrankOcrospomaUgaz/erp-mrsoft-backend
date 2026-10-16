<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoDocumento extends Model
{
    protected $table = 'contrato_documentos';

    protected $fillable = [
        'contrato_id',
        'nombre_original',
        'ruta',
        'mime_type',
        'tamanio',
    ];

    protected $casts = [
        'tamanio' => 'integer',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
