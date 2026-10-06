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
        if ($cuota->kuti_payment_intent_id !== $request->payment_intent_id) {
            return response()->json(['message' => 'Cobro no asociado a esta cuota'], 422);
        }

        $response = Http::withToken(config('services.kuti.secret_key'))
            ->acceptJson()->timeout((int) config('services.kuti.timeout', 20))
            ->get(config('services.kuti.base_url') . '/payment-intents/' . $request->payment_intent_id);
        $intent = $response->json('data', []);
        if ($response->failed() || ($intent['status'] ?? null) !== 'SUCCEEDED') {
            return response()->json(['message' => 'Kuti aún no confirma el pago.'], 409);
        }

        DB::transaction(function () use ($cuota, $intent) {
            if (PagosCuotum::where('comprobante', 'KUTI:' . $intent['id'])->exists()) return;
            $paid = (float) $cuota->pagos_cuota->sum('monto_pagado');
            $amount = min((float) ($intent['amount']['amount'] ?? 0), round((float) $cuota->monto - $paid, 2));
            if ($amount <= 0) return;
            PagosCuotum::create(['cuota_id' => $cuota->id, 'fecha_pago' => now(), 'monto_pagado' => $amount, 'comprobante' => 'KUTI:' . $intent['id']]);
            $total = round($paid + $amount, 2);
            $cuota->update(['situacion' => $total >= round((float) $cuota->monto, 2) ? 'pagado' : 'pendiente', 'fecha_pago' => $total >= round((float) $cuota->monto, 2) ? now() : null, 'kuti_payment_status' => 'SUCCEEDED']);
        });
        return response()->json(['message' => 'Pago confirmado']);
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
