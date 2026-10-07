<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Models\Contrato;
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
        if ($payload['type'] === 'subscription.payment_succeeded') {
            $this->applySubscriptionPayment($payload['data']['subscription'] ?? []);
            return response()->json(['ok' => true]);
        }
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

    private function applySubscriptionPayment(array $subscription): void
    {
        $contractId = (int) data_get($subscription, 'metadata.contrato_id');
        $intentId = data_get($subscription, 'latest_cycle.payment_intent_id');
        $amount = (float) data_get($subscription, 'latest_cycle.amount.amount', 0);
        if (!$contractId || !$intentId || $amount <= 0) return;

        DB::transaction(function () use ($contractId, $intentId, $amount, $subscription) {
            $contract = Contrato::lockForUpdate()->with(['cuotas.pagos_cuota'])->find($contractId);
            if (!$contract) return;
            $quota = $contract->cuotas
                ->sortBy('fecha_vencimiento')
                ->first(fn ($item) => round((float) $item->monto - (float) $item->pagos_cuota->sum('monto_pagado'), 2) > 0);
            if (!$quota || PagosCuotum::where('comprobante', 'KUTI:' . $intentId)->exists()) return;

            $paid = (float) $quota->pagos_cuota->sum('monto_pagado');
            $toApply = min($amount, round((float) $quota->monto - $paid, 2));
            if ($toApply <= 0) return;
            PagosCuotum::create([
                'cuota_id' => $quota->id,
                'fecha_pago' => now(),
                'monto_pagado' => $toApply,
                'comprobante' => 'KUTI:' . $intentId,
            ]);
            $total = round($paid + $toApply, 2);
            $quota->update([
                'situacion' => $total >= round((float) $quota->monto, 2) ? 'pagado' : 'pendiente',
                'fecha_pago' => $total >= round((float) $quota->monto, 2) ? now() : null,
            ]);
            $contract->update([
                'kuti_subscription_status' => data_get($subscription, 'status', 'ACTIVE'),
                'kuti_subscription_next_charge_at' => data_get($subscription, 'next_charge_at'),
                'kuti_subscription_amount' => data_get($subscription, 'amount.amount', $contract->kuti_subscription_amount),
            ]);
        });
    }
}
