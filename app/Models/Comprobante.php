<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Services\Facturacion\CdrInspector;

class Comprobante extends Model
{
    use SoftDeletes;

    protected $table = 'comprobantes';

    protected $fillable = [
        'cliente_id',
        'receptor_documento',
        'receptor_nombre',
        'receptor_direccion',
        'contrato_id',
        'cuota_id',
        'facturador_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'moneda',
        'forma_pago',
        'fecha_emision',
        'hora_emision',
        'subtotal',
        'igv',
        'total',
        'estado',
        'payload',
        'sunat_request',
        'sunat_response',
        'solicitud_facturador_id',
        'nombre_documento',
        'xml_path',
        'cdr_path',
        'zip_path',
        'pdf_path',
        'error_code',
        'error_text',
        'fecha_envio',
        'fecha_respuesta',
        'estado_envio_cliente',
        'fecha_envio_cliente',
        'celular_envio_cliente',
        'error_envio_cliente',
    ];

    protected $casts = [
        'fecha_emision' => 'date:Y-m-d',
        'fecha_envio' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_envio_cliente' => 'datetime',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'payload' => 'array',
        'sunat_request' => 'array',
        'sunat_response' => 'array',
    ];

    public function tieneCdrAceptado(): bool
    {
        $archivosZip = [];

        if ($this->zip_path && Storage::disk('local')->exists($this->zip_path)) {
            $archivosZip[] = Storage::disk('local')->get($this->zip_path);
        }

        $zipBase64 = data_get($this->sunat_response, 'fileZIPBASE64');
        if (is_string($zipBase64) && $zipBase64 !== '') {
            $zip = base64_decode($zipBase64, true);
            if ($zip !== false) {
                $archivosZip[] = $zip;
            }
        }

        foreach ($archivosZip as $zip) {
            if (CdrInspector::contieneAceptacion($zip, (string) $this->serie, (int) $this->correlativo)) {
                return true;
            }
        }

        return false;
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class);
    }

    public function facturador()
    {
        return $this->belongsTo(Facturador::class);
    }

    public function detalles()
    {
        return $this->hasMany(ComprobanteDetalle::class);
    }
}
