<?php

namespace App\Services;

use App\Models\Cuota;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KutiPaymentService
{
    public function createCheckout(Cuota $cuota): array
    {
        $cuota->loadMissing(['contrato.cliente', 'pagos_cuota']);
        $pending = round((float) $cuota->monto - (float) $cuota->pagos_cuota->sum('monto_pagado'), 2);

        if ($pending <= 0 || $cuota->situacion === 'pagado') {
            throw new \RuntimeException('Esta cuota ya está pagada.');
        }

        if ($cuota->kuti_checkout_url && $cuota->kuti_payment_status === 'PENDING') {
            return [
                'checkout_url' => $cuota->kuti_checkout_url,
                'payment_intent_id' => $cuota->kuti_payment_intent_id,
                'amount' => number_format($pending, 2, '.', ''),
            ];
        }

        $cliente = $cuota->contrato->cliente;
        $email = $cliente->dueno_email ?: $cliente->representante_email;
        $phone = $cliente->dueno_celular ?: $cliente->representante_celular;
        $isCompany = !empty($cliente->ruc);

        $customer = [
            'type' => $isCompany ? 'COMPANY' : 'INDIVIDUAL',
            'external_id' => 'erp-cliente-' . $cliente->id,
            'email' => $email ?: null,
            'phone' => $this->phone($phone),
            'document' => $cliente->ruc
                ? ['type' => 'RUC', 'number' => (string) $cliente->ruc, 'country' => 'PE']
                : null,
        ];

        if ($isCompany) {
            $customer['company_name'] = $cliente->razon_social ?: $cliente->nombre_comercial ?: 'Cliente ERP';
        } else {
            $parts = preg_split('/\s+/', trim($cliente->dueno_nombre ?: 'Cliente'), 2);
            $customer['first_name'] = $parts[0];
            $customer['last_name'] = $parts[1] ?? '';
        }

        $reference = 'cuota-' . $cuota->id . '-' . Str::uuid();
        $payload = [
                'amount' => ['amount' => number_format($pending, 2, '.', ''), 'currency' => 'PEN'],
                'payment_method_types' => ['INTEROPERABLE_QR', 'BANK_TRANSFER'],
                'customer' => array_filter($customer, fn ($value) => $value !== null && $value !== ''),
                'description' => 'Cuota ' . $cuota->id . ' - contrato ' . ($cuota->contrato->numero ?? $cuota->contrato_id),
                'external_reference' => $reference,
                'metadata' => ['cuota_id' => (string) $cuota->id],
            ];
        if (config('services.kuti.success_url')) $payload['success_url'] = config('services.kuti.success_url');

        $response = Http::withToken(config('services.kuti.secret_key'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->timeout((int) config('services.kuti.timeout', 20))
            ->post(config('services.kuti.base_url') . '/checkout-sessions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Kuti no pudo crear el checkout: ' . $response->body());
        }

        $data = $response->json('data', []);
        $cuota->update([
            'kuti_payment_intent_id' => $data['payment_intent_id'] ?? null,
            'kuti_checkout_url' => $data['checkout_url'] ?? null,
            'kuti_payment_status' => $data['status'] ?? 'PENDING',
        ]);

        return [
            'checkout_url' => $data['checkout_url'] ?? null,
            'payment_intent_id' => $data['payment_intent_id'] ?? null,
            'amount' => number_format($pending, 2, '.', ''),
        ];
    }

    private function phone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits ? (str_starts_with($digits, '51') ? '+' : '+51') . $digits : null;
    }
}
