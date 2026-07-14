<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoLocal extends Model
{
    use SoftDeletes;

    protected $table = 'tipos_locales';

    protected $fillable = [
        'nombre',
        'codigo',
    ];
}
