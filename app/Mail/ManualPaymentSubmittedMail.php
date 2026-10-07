<?php

namespace App\Mail;

use App\Models\PagosCuotum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ManualPaymentSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PagosCuotum $pago, public string $reviewUrl) {}

    public function build(): self
    {
        $this->pago->loadMissing('cuota.contrato.cliente');
        $cliente = $this->pago->cuota?->contrato?->cliente;
        $contrato = $this->pago->cuota?->contrato?->numero ?: '—';
        $importe = number_format((float) $this->pago->monto_pagado, 2);
        $nombre = e($cliente?->razon_social ?: $cliente?->nombre_comercial ?: $cliente?->dueno_nombre ?: 'Cliente');

        return $this->subject('Nuevo comprobante pendiente de revisión')
            ->html("<div style=\"font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#172033\"><div style=\"background:#071b32;padding:28px;border-radius:14px 14px 0 0;color:#fff\"><p style=\"margin:0;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7\">Mr. Soft · Revisión de pagos</p><h1 style=\"margin:12px 0 0;font-size:24px\">Nuevo comprobante recibido</h1></div><div style=\"border:1px solid #e5e7eb;border-top:0;padding:28px;border-radius:0 0 14px 14px\"><p>Se recibió un comprobante manual que requiere validación.</p><table style=\"width:100%;border-collapse:collapse;margin:22px 0\"><tr><td style=\"padding:10px 0;color:#64748b\">Cliente</td><td style=\"padding:10px 0;text-align:right;font-weight:600\">{$nombre}</td></tr><tr><td style=\"padding:10px 0;color:#64748b\">Contrato</td><td style=\"padding:10px 0;text-align:right;font-weight:600\">" . e($contrato) . "</td></tr><tr><td style=\"padding:10px 0;color:#64748b\">Importe</td><td style=\"padding:10px 0;text-align:right;font-weight:700\">S/ {$importe}</td></tr></table><a href=\"" . e($this->reviewUrl) . "\" style=\"display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:700\">Revisar comprobante</a><p style=\"margin:22px 0 0;color:#64748b;font-size:12px\">Si no tienes una sesión activa, el sistema te pedirá iniciar sesión antes de mostrar la revisión.</p></div></div>");
    }
}
