<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Services\KutiPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\PagosCuotum;

class KutiPaymentController extends Controller
{
    public function checkout(Request $request, Cuota $cuota, KutiPaymentService $kuti)
    {
        $cuota->load('contrato.cliente');
        $user = $request->user();
        if ($user?->cliente_id && !$this->canAccess($user->cliente_id, $cuota)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            return response()->json(['data' => $kuti->createCheckout($cuota->fresh())]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request, Cuota $cuota)
    {
        $request->validate(['payment_intent_id' => 'required|string']);
        $cuota->load('contrato.cliente', 'pagos_cuota');
        if ($request->user()?->cliente_id && !$this->canAccess($request->user()->cliente_id, $cuota)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $response = Http::withToken(config('services.kuti.secret_key'))
            ->acceptJson()->timeout((int) config('services.kuti.timeout', 20))
            ->get(config('services.kuti.base_url') . '/payment-intents/' . $request->payment_intent_id);
        $intent = $response->json('data', []);
        if (!is_array($intent) || !$intent) {
            $intent = $response->json() ?: [];
        }
        if (isset($intent['payment_intent']) && is_array($intent['payment_intent'])) {
            $intent = $intent['payment_intent'];
        }
        $intentStatus = strtoupper((string) ($intent['status'] ?? $intent['payment_status'] ?? ''));
        if ($response->failed() || $intentStatus !== 'SUCCEEDED') {
            return response()->json(['message' => 'Kuti aún no confirma el pago.'], 409);
        }

        $providerIntentId = (string) ($intent['id'] ?? $request->payment_intent_id);
        $updatedCuota = DB::transaction(function () use ($cuota, $intent, $request, $providerIntentId) {
            $lockedCuota = Cuota::lockForUpdate()->findOrFail($cuota->id);
            $intentId = $providerIntentId;
            $comprobante = 'KUTI:' . $intentId;

            if (!PagosCuotum::where('comprobante', $comprobante)->exists()) {
                $alreadyPaid = (float) $lockedCuota->pagos_cuota()->sum('monto_pagado');
                $amountValue = is_array($intent['amount'] ?? null)
                    ? ($intent['amount']['amount'] ?? 0)
                    : ($intent['amount'] ?? 0);
                $amount = min((float) $amountValue, round((float) $lockedCuota->monto - $alreadyPaid, 2));

                if ($amount > 0) {
                    PagosCuotum::create([
                        'cuota_id' => $lockedCuota->id,
                        'fecha_pago' => now(),
                        'monto_pagado' => $amount,
                        'comprobante' => $comprobante,
                    ]);
                }
            }

            $totalPaid = round((float) $lockedCuota->pagos_cuota()->sum('monto_pagado'), 2);
            $isPaid = $totalPaid >= round((float) $lockedCuota->monto, 2);
            $lockedCuota->update([
                'situacion' => $isPaid ? 'pagado' : 'pendiente',
                'fecha_pago' => $isPaid ? ($lockedCuota->fecha_pago ?: now()) : null,
                'kuti_payment_status' => 'SUCCEEDED',
                'kuti_payment_intent_id' => $intentId,
            ]);

            return $lockedCuota->fresh();
        });
        return response()->json(['message' => 'Pago confirmado', 'data' => [
            'cuota_id' => $updatedCuota->id,
            'situacion' => $updatedCuota->situacion,
            'fecha_pago' => $updatedCuota->fecha_pago?->toISOString(),
        ]]);
    }

    private function canAccess(int $clienteId, Cuota $cuota): bool
    {
        $owner = $cuota->contrato?->cliente;
        while ($owner) {
            if ((int) $owner->id === $clienteId) return true;
            $owner = $owner->parent_cliente;
        }
        return false;
    }
}
