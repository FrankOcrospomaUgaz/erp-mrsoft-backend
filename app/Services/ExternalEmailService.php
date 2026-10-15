<?php

namespace App\Services;

use App\Models\PagosCuotum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ExternalEmailService
{
    public function send(array $to, string $subject, string $body, array $cc = [], array $bcc = [], array $attachments = []): array
    {
        $endpoint = config('services.email_api.endpoint');
        $token = config('services.email_api.token');

        if (!$endpoint || !$token) {
            throw new \RuntimeException('El servicio de correo no está configurado. Falta COMPROBANTES_MAIL_TOKEN.');
        }

        $payload = [
            'token' => $token,
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
            ->post($endpoint, $payload);

        $data = $response->json() ?: [];
        $providerCode = array_key_exists('code', $data) ? (int) $data['code'] : null;
        if ($response->status() !== 200 || $providerCode !== 0) {
            throw new \RuntimeException('La API de correo rechazó el mensaje: ' . $response->body());
        }

        return $data ?: ['success' => true];
    }

    public function notifyManualPaymentSubmitted(PagosCuotum $pago, string $reviewUrl, string $recipient): array
    {
        $pago->loadMissing('cuota.contrato.cliente');
        $cliente = $pago->cuota?->contrato?->cliente;
        $cuota = $pago->cuota;
        $contrato = $cuota?->contrato;
        $name = e($cliente?->razon_social ?: $cliente?->nombre_comercial ?: $cliente?->dueno_nombre ?: 'Cliente');
        $ruc = e($cliente?->ruc ?: 'No registrado');
        $contractNumber = e($contrato?->numero ?: '—');
        $contractSubject = $contrato?->numero ?: 'Sin contrato';
        $amount = number_format((float) $pago->monto_pagado, 2);
        $quotaAmount = number_format((float) ($cuota?->monto ?: 0), 2);
        $dueDate = e($this->formatDate($cuota?->fecha_vencimiento));
        $period = e($this->formatPeriod($cuota?->fecha_vencimiento));
        $reportedDate = e($this->formatDate($pago->created_at, true));
        $situation = e(ucfirst((string) ($cuota?->situacion ?: 'pendiente')));
        $details = '<div style="margin:20px 0;padding:16px;background:#f8fafc;border-left:4px solid #0f766e"><p style="margin:0 0 8px;color:#475569;font-size:12px"><strong>CLIENTE</strong></p><p style="margin:0;font-size:17px"><strong>' . $name . '</strong></p><p style="margin:5px 0 0;color:#64748b">RUC: ' . $ruc . '</p></div><p style="margin:20px 0 8px;color:#475569;font-size:12px"><strong>DETALLE DE LA CUOTA</strong></p><p style="margin:0;line-height:1.8;color:#334155"><strong>Contrato:</strong> ' . $contractNumber . '<br><strong>Cuota:</strong> #' . e((string) ($cuota?->id ?: '—')) . '<br><strong>Periodo:</strong> ' . $period . '<br><strong>Vencimiento:</strong> ' . $dueDate . '<br><strong>Estado actual:</strong> ' . $situation . '<br><strong>Importe de la cuota:</strong> S/ ' . $quotaAmount . '<br><strong>Importe reportado:</strong> <span style="color:#0f766e"><strong>S/ ' . $amount . '</strong></span></p><p style="margin:16px 0 22px;color:#64748b;font-size:12px">Comprobante enviado el ' . $reportedDate . '.</p>';
        $body = $this->layout('Nuevo comprobante recibido', '#071b32', '<p>Se recibió un comprobante manual que requiere validación.</p>' . $details . '<a href="' . e($reviewUrl) . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:700">Revisar comprobante</a><p style="margin:22px 0 0;color:#64748b;font-size:12px">Si no tienes una sesión activa, el sistema te pedirá iniciar sesión antes de mostrar la revisión.</p>');
        return $this->send([$recipient], 'Comprobante pendiente #' . $pago->id . ' | Contrato ' . $contractSubject . ' | ' . $this->formatPeriod($cuota?->fecha_vencimiento), $body);
    }

    private function formatDate($date, bool $withTime = false): string
    {
        if (!$date) return 'No registrado';
        $format = $withTime ? 'd/m/Y H:i' : 'd/m/Y';
        $carbon = Carbon::parse($date);
        if ($withTime) {
            $carbon = $carbon->setTimezone(config('app.timezone', 'America/Lima'));
        }
        return $carbon->format($format);
    }

    private function formatPeriod($date): string
    {
        if (!$date) return 'Periodo no registrado';
        return ucfirst(Carbon::parse($date)->locale('es')->translatedFormat('F Y'));
    }

    public function notifyManualPaymentDecision(PagosCuotum $pago, bool $approved, ?string $comment, string $recipient): array
    {
        $pago->loadMissing('cuota.contrato');
        $title = $approved ? 'Comprobante aprobado' : 'Comprobante rechazado';
        $color = $approved ? '#047857' : '#b91c1c';
        $message = $approved ? 'Tu pago fue validado y la cuota ya fue actualizada.' : 'Tu comprobante no pudo ser validado. Puedes revisar el comentario y enviar uno nuevo.';
        $commentHtml = $comment ? '<p style="margin:20px 0 0;padding:14px;background:#f8fafc;border-left:4px solid ' . $color . ';line-height:1.6"><strong>Comentario de revisión:</strong><br>' . nl2br(e($comment)) . '</p>' : '';
        $body = $this->layout($title, $color, '<p>' . $message . '</p><p style="color:#64748b"><strong>Contrato:</strong> ' . e($pago->cuota?->contrato?->numero ?: '—') . '<br><strong>Importe:</strong> S/ ' . number_format((float) $pago->monto_pagado, 2) . '</p>' . $commentHtml . '<p style="margin:24px 0 0;color:#64748b;font-size:12px">Este mensaje fue generado automáticamente.</p>');
        return $this->send([$recipient], $title, $body);
    }

    public function notifyPortalCredentials(string $recipient, string $name, string $username, string $password, bool $reset = false): array
    {
        $title = $reset ? 'Nueva clave de acceso' : 'Credenciales de acceso al portal';
        $loginUrl = config('services.email_api.frontend_url') . '/login';
        $changeUrl = config('services.email_api.frontend_url') . '/inicio?cambiar-clave=1';
        $content = '<p>Hola <strong>' . e($name) . '</strong>,</p>'
            . '<p>' . ($reset ? 'Se generó una nueva clave para tu acceso.' : 'Se creó tu acceso al portal de Mr. Soft.') . '</p>'
            . '<div style="margin:20px 0;padding:16px;background:#f8fafc;border-left:4px solid #0f766e;line-height:1.8">'
            . '<strong>Usuario:</strong> ' . e($username) . '<br><strong>Clave temporal:</strong> ' . e($password) . '</div>'
            . '<p>Por seguridad, cambia esta clave después de ingresar:</p>'
            . '<a href="' . e($changeUrl) . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:700">Cambiar mi clave</a>'
            . '<p style="margin:14px 0 0;color:#64748b;font-size:12px">También puedes ingresar desde: ' . e($loginUrl) . '</p>';

        return $this->send([$recipient], $title, $this->layout($title, '#071b32', $content));
    }

    private function layout(string $title, string $color, string $content): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#172033"><div style="background:' . $color . ';padding:28px;border-radius:14px 14px 0 0;color:#fff"><p style="margin:0;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.8">Mr. Soft · Pagos</p><h1 style="margin:12px 0 0;font-size:24px">' . e($title) . '</h1></div><div style="border:1px solid #e5e7eb;border-top:0;padding:28px;border-radius:0 0 14px 14px">' . $content . '</div></div>';
    }
}
