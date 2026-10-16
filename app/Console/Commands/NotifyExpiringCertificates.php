<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\ContratoDocumento;
use App\Services\ExternalEmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyExpiringCertificates extends Command
{
    protected $signature = 'certificados:notificar-vencimientos';
    protected $description = 'Notifica al equipo sobre certificados digitales próximos a vencer';

    public function handle(ExternalEmailService $emailService): int
    {
        $recipient = Configuracion::where('clave', 'manual_payment_notification_email')->value('valor')
            ?: env('PAYMENT_REVIEW_EMAIL', 'admin@garzasoft.com');
        $today = Carbon::today();
        $limit = $today->copy()->addDays(30);

        $documents = ContratoDocumento::with('contrato.cliente')
            ->where('categoria', 'certificado_digital')
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$today->toDateString(), $limit->toDateString()])
            ->where(function ($query) use ($today) {
                $query->whereNull('ultima_alerta_at')
                    ->orWhereDate('ultima_alerta_at', '<', $today->toDateString());
            })
            ->get();

        foreach ($documents as $document) {
            $client = $document->contrato?->cliente;
            $name = e($client?->razon_social ?: $client?->nombre_comercial ?: 'Cliente');
            $contract = e($document->contrato?->numero ?: '—');
            $date = Carbon::parse($document->fecha_vencimiento)->format('d/m/Y');
            $days = $today->diffInDays(Carbon::parse($document->fecha_vencimiento), false);
            $body = '<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#172033">'
                . '<div style="background:#92400e;padding:28px;color:#fff;border-radius:14px 14px 0 0"><h1 style="margin:0;font-size:22px">Certificado digital próximo a vencer</h1></div>'
                . '<div style="border:1px solid #e5e7eb;border-top:0;padding:28px;border-radius:0 0 14px 14px">'
                . '<p>El certificado digital requiere renovación preventiva.</p>'
                . '<p><strong>Cliente:</strong> ' . $name . '<br><strong>Contrato:</strong> ' . $contract . '<br><strong>Vencimiento:</strong> ' . $date . '<br><strong>Días restantes:</strong> ' . $days . '</p>'
                . '<p style="color:#64748b;font-size:12px">Este aviso fue generado automáticamente por Mr. Soft.</p></div></div>';

            try {
                $emailService->send([$recipient], 'Certificado digital próximo a vencer | Contrato ' . $contract, $body);
                $document->forceFill(['ultima_alerta_at' => now()])->save();
                $this->info("Aviso enviado: {$contract}");
            } catch (\Throwable $exception) {
                report($exception);
                $this->error("No se pudo notificar: {$contract}");
            }
        }

        return self::SUCCESS;
    }
}
