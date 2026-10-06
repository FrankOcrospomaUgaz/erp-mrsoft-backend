<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Models\PagosCuotum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KutiWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $raw = $request->getContent();
        $timestamp = (string) $request->header('X-Kuti-Timestamp');
        $signature = (string) $request->header('X-Kuti-Signature');
        $expected = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $raw, (string) config('services.kuti.webhook_secret'));

        if (!$timestamp || abs(time() - (int) $timestamp) > 300 || !$signature || !hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Firma inválida'], 400);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) return response()->json(['message' => 'Payload inválido'], 400);
        if ($payload['type'] !== 'payment.succeeded') return response()->json(['ok' => true]);

        $intent = $payload['data']['payment_intent'] ?? [];
        $reference = (string) ($intent['external_reference'] ?? '');
        if (!preg_match('/^cuota-(\d+)-/', $reference, $matches)) return response()->json(['ok' => true]);

        DB::transaction(function () use ($matches, $intent) {
            $cuota = Cuota::lockForUpdate()->with('pagos_cuota')->find((int) $matches[1]);
            if (!$cuota || !$cuota->contrato || $cuota->kuti_payment_intent_id !== ($intent['id'] ?? null)) return;
            if (PagosCuotum::where('comprobante', 'KUTI:' . $intent['id'])->exists()) return;

            $alreadyPaid = (float) $cuota->pagos_cuota->sum('monto_pagado');
            $amount = (float) ($intent['amount']['amount'] ?? 0);
            $remaining = round((float) $cuota->monto - $alreadyPaid, 2);
            $paymentAmount = min($amount, $remaining);
            if ($paymentAmount <= 0) return;

            PagosCuotum::create([
                'cuota_id' => $cuota->id,
                'fecha_pago' => now(),
                'monto_pagado' => $paymentAmount,
                'comprobante' => 'KUTI:' . $intent['id'],
            ]);
            $total = round($alreadyPaid + $paymentAmount, 2);
            $cuota->update([
                'situacion' => $total >= round((float) $cuota->monto, 2) ? 'pagado' : 'pendiente',
                'fecha_pago' => $total >= round((float) $cuota->monto, 2) ? now() : null,
                'kuti_payment_status' => 'SUCCEEDED',
            ]);
        });

        return response()->json(['ok' => true]);
    }
}
