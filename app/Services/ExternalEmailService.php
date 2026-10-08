<?php

namespace App\Services;

use App\Models\PagosCuotum;
use Illuminate\Support\Facades\Http;

class ExternalEmailService
{
    public function send(array $to, string $subject, string $body, array $cc = [], array $bcc = [], array $attachments = []): array
    {
        $payload = [
            'token' => config('services.email_api.token'),
            'to' => array_values($to),
            'bcc' => array_values($bcc),
            'cc' => array_values($cc),
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ];

        $response = Http::asJson()
            ->acceptJson()
            ->timeout((int) config('services.email_api.timeout', 20))
            ->post(config('services.email_api.endpoint'), $payload);

        if ($response->failed() || $response->json('success') === false) {
            throw new \RuntimeException('La API de correo rechazó el mensaje: ' . $response->body());
        }

        return $response->json() ?: ['success' => true];
    }

    public function notifyManualPaymentSubmitted(PagosCuotum $pago, string $reviewUrl, string $recipient): array
    {
        $pago->loadMissing('cuota.contrato.cliente');
        $cliente = $pago->cuota?->contrato?->cliente;
        $name = e($cliente?->razon_social ?: $cliente?->nombre_comercial ?: $cliente?->dueno_nombre ?: 'Cliente');
        $contract = e($pago->cuota?->contrato?->numero ?: '—');
        $amount = number_format((float) $pago->monto_pagado, 2);
        $body = $this->layout('Nuevo comprobante recibido', '#071b32', '<p>Se recibió un comprobante manual que requiere validación.</p><table style="width:100%;border-collapse:collapse;margin:22px 0"><tr><td style="padding:10px 0;color:#64748b">Cliente</td><td style="padding:10px 0;text-align:right;font-weight:600">' . $name . '</td></tr><tr><td style="padding:10px 0;color:#64748b">Contrato</td><td style="padding:10px 0;text-align:right;font-weight:600">' . $contract . '</td></tr><tr><td style="padding:10px 0;color:#64748b">Importe</td><td style="padding:10px 0;text-align:right;font-weight:700">S/ ' . $amount . '</td></tr></table><a href="' . e($reviewUrl) . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:700">Revisar comprobante</a><p style="margin:22px 0 0;color:#64748b;font-size:12px">Si no tienes una sesión activa, el sistema te pedirá iniciar sesión antes de mostrar la revisión.</p>');
        return $this->send([$recipient], 'Nuevo comprobante pendiente de revisión', $body);
    }

    public function notifyManualPaymentDecision(PagosCuotum $pago, bool $approved, ?string $comment, string $recipient): array
    {
        $pago->loadMissing('cuota.contrato');
        $title = $approved ? 'Comprobante aprobado' : 'Comprobante rechazado';
        $color = $approved ? '#047857' : '#b91c1c';
        $message = $approved ? 'Tu pago fue validado y la cuota ya fue actualizada.' : 'Tu comprobante no pudo ser validado. Puedes revisar el comentario y enviar uno nuevo.';
        $commentHtml = $comment ? '<div style="margin-top:18px;padding:14px;background:#f8fafc;border-radius:8px"><strong>Comentario:</strong><br>' . nl2br(e($comment)) . '</div>' : '';
        $body = $this->layout($title, $color, '<p>' . $message . '</p><p style="color:#64748b">Contrato: <strong>' . e($pago->cuota?->contrato?->numero ?: '—') . '</strong><br>Importe: <strong>S/ ' . number_format((float) $pago->monto_pagado, 2) . '</strong></p>' . $commentHtml . '<p style="margin:24px 0 0;color:#64748b;font-size:12px">Este mensaje fue generado automáticamente.</p>');
        return $this->send([$recipient], $title, $body);
    }

    private function layout(string $title, string $color, string $content): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#172033"><div style="background:' . $color . ';padding:28px;border-radius:14px 14px 0 0;color:#fff"><p style="margin:0;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.8">Mr. Soft · Pagos</p><h1 style="margin:12px 0 0;font-size:24px">' . e($title) . '</h1></div><div style="border:1px solid #e5e7eb;border-top:0;padding:28px;border-radius:0 0 14px 14px">' . $content . '</div></div>';
    }
}
