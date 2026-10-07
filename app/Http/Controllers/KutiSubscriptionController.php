<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KutiSubscriptionController extends Controller
{
    public function store(Request $request, Contrato $contrato)
    {
        $contrato->load(['cliente', 'cuotas.pagos_cuota']);
        if ($request->user()?->cliente_id && !$this->canAccess($request->user()->cliente_id, $contrato->cliente)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        if ($contrato->estado !== 'activo') return response()->json(['message' => 'El contrato no está activo.'], 422);
        if ($contrato->kuti_subscription_status === 'ACTIVE') return response()->json(['message' => 'La suscripción ya está activa.'], 409);
        if ($contrato->kuti_subscription_id && $contrato->kuti_subscription_checkout_url) {
            return response()->json(['data' => [
                'subscription_id' => $contrato->kuti_subscription_id,
                'status' => $contrato->kuti_subscription_status ?: 'INCOMPLETE',
                'checkout_url' => $contrato->kuti_subscription_checkout_url,
                'amount' => number_format((float) $contrato->kuti_subscription_amount, 2, '.', ''),
                'frequency' => $contrato->kuti_subscription_frequency,
                'next_charge_at' => $contrato->kuti_subscription_next_charge_at?->toIso8601String(),
                'message' => 'Continúa la afiliación pendiente en Kuti.',
            ]]);
        }

        $quota = $contrato->cuotas->first(fn ($item) => (float) $item->monto > (float) $item->pagos_cuota->sum('monto_pagado'));
        if (!$quota) return response()->json(['message' => 'No hay cuotas pendientes para suscribir.'], 422);

        $cliente = $contrato->cliente;
        $customer = [
            'type' => $cliente->ruc ? 'COMPANY' : 'INDIVIDUAL',
            'external_id' => 'erp-cliente-' . $cliente->id,
            'email' => $cliente->dueno_email ?: $cliente->representante_email,
            'phone' => $this->phone($cliente->dueno_celular ?: $cliente->representante_celular),
            'document' => $cliente->ruc ? ['type' => 'RUC', 'number' => (string) $cliente->ruc, 'country' => 'PE'] : null,
        ];
        if ($cliente->ruc) {
            $customer['company_name'] = $cliente->razon_social ?: $cliente->nombre_comercial ?: 'Cliente ERP';
        } else {
            $parts = preg_split('/\s+/', trim($cliente->dueno_nombre ?: 'Cliente'), 2);
            $customer['first_name'] = $parts[0];
            $customer['last_name'] = $parts[1] ?? '';
        }

        $frequency = $contrato->periodicidad_cuota === 'anual' ? 'YEARLY' : 'MONTHLY';
        $chargeTime = config('services.kuti.charge_time', '09:00');
        // Kuti exige que el primer periodo empiece hoy cuando el cliente aún
        // no tiene un medio guardado: así puede afiliar Yape y aprobar el primer cobro.
        // Los siguientes periodos se generan automáticamente según frequency.
        $startDate = now()->toDateString();
        $dayOfMonth = min((int) now()->day, 28);
        $lastDayOfMonth = (int) now()->day > 28;
        $amount = round((float) $quota->monto - (float) $quota->pagos_cuota->sum('monto_pagado'), 2);
        $payload = [
            'customer' => array_filter($customer, fn ($value) => $value !== null && $value !== ''),
            'description' => 'Suscripción contrato ' . $contrato->numero,
            'amount' => number_format($amount, 2, '.', ''),
            'frequency' => $frequency,
            'start_date' => $startDate,
            'end_date' => $contrato->fecha_fin?->toDateString(),
            'charge_time' => $chargeTime,
            'external_reference' => 'contrato-' . $contrato->id,
            'metadata' => ['contrato_id' => (string) $contrato->id, 'cuota_id' => (string) $quota->id],
            'send_via' => [],
        ];
        if ($frequency === 'MONTHLY') {
            if ($lastDayOfMonth) $payload['last_day_of_month'] = true;
            else $payload['day_of_month'] = $dayOfMonth;
        }

        $idempotencyKey = 'contrato-' . $contrato->id . '-subscription-' . substr(hash('sha256', json_encode($payload)), 0, 24);
        $response = Http::withToken(config('services.kuti.secret_key'))->acceptJson()->asJson()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->timeout((int) config('services.kuti.timeout', 20))
            ->post(config('services.kuti.base_url') . '/subscriptions', $payload);

        if ($response->failed()) return response()->json(['message' => 'Kuti no pudo crear la suscripción: ' . $response->body()], 422);

        $data = $response->json('data', []);
        $checkoutUrl = data_get($data, 'latest_cycle.checkout_url') ?: ($data['setup_url'] ?? null);
        $contrato->update([
            'kuti_subscription_id' => $data['id'] ?? null,
            'kuti_subscription_status' => $data['status'] ?? 'INCOMPLETE',
            'kuti_subscription_checkout_url' => $checkoutUrl,
            'kuti_subscription_frequency' => $frequency,
            'kuti_subscription_amount' => $amount,
            'kuti_subscription_next_charge_at' => $data['next_charge_at'] ?? null,
            'kuti_subscription_charge_time' => $chargeTime,
        ]);

        return response()->json(['data' => [
            'subscription_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'INCOMPLETE',
            'checkout_url' => $checkoutUrl,
            'amount' => number_format($amount, 2, '.', ''),
            'frequency' => $frequency,
            'start_date' => $startDate,
            'end_date' => $contrato->fecha_fin?->toDateString(),
            'charge_time' => $chargeTime,
            'next_charge_at' => $data['next_charge_at'] ?? null,
            'message' => 'El primer cobro se realizará hoy para afiliar Yape; luego Kuti cobrará automáticamente según la frecuencia configurada.',
        ]]);
    }

    private function canAccess(int $clienteId, $cliente): bool
    {
        while ($cliente) {
            if ((int) $cliente->id === $clienteId) return true;
            $cliente = $cliente->parent_cliente;
        }
        return false;
    }

    private function phone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits ? (str_starts_with($digits, '51') ? '+' : '+51') . $digits : null;
    }
}
