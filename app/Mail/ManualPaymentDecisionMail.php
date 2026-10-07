<?php

namespace App\Mail;

use App\Models\PagosCuotum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ManualPaymentDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PagosCuotum $pago, public bool $approved, public ?string $comment = null) {}

    public function build(): self
    {
        $this->pago->loadMissing('cuota.contrato');
        $status = $this->approved ? 'Comprobante aprobado' : 'Comprobante rechazado';
        $color = $this->approved ? '#047857' : '#b91c1c';
        $message = $this->approved ? 'Tu pago fue validado y la cuota ya fue actualizada.' : 'Tu comprobante no pudo ser validado. Puedes revisar el comentario y enviar uno nuevo.';
        $comment = $this->comment ? '<div style="margin-top:18px;padding:14px;background:#f8fafc;border-radius:8px"><strong>Comentario:</strong><br>' . nl2br(e($this->comment)) . '</div>' : '';
        return $this->subject($status)
            ->html('<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#172033"><div style="background:' . $color . ';padding:28px;border-radius:14px 14px 0 0;color:#fff"><p style="margin:0;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.8">Mr. Soft · Pagos</p><h1 style="margin:12px 0 0;font-size:24px">' . $status . '</h1></div><div style="border:1px solid #e5e7eb;border-top:0;padding:28px;border-radius:0 0 14px 14px"><p>' . $message . '</p><p style="color:#64748b">Contrato: <strong>' . e($this->pago->cuota?->contrato?->numero ?: '—') . '</strong><br>Importe: <strong>S/ ' . number_format((float) $this->pago->monto_pagado, 2) . '</strong></p>' . $comment . '<p style="margin:24px 0 0;color:#64748b;font-size:12px">Este mensaje fue generado automáticamente.</p></div></div>');
    }
}
