<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Cuota;
use App\Models\PagosCuotum;
use App\Models\SaasBranchIntegration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class SaasIntegrationController extends Controller
{
    public function settings()
    {
        return response()->json(['data' => [
            'days_before_due' => (int) (Configuracion::where('clave', 'saas_notice_days_before_due')->value('valor') ?? 3),
            'suspension_alert_days' => (int) (Configuracion::where('clave', 'saas_suspension_alert_days')->value('valor') ?? 7),
        ]]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'days_before_due' => ['required', 'integer', 'min:0', 'max:90'],
            'suspension_alert_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);
        foreach ($data as $key => $value) {
            Configuracion::updateOrCreate(['clave' => 'saas_'.($key === 'days_before_due' ? 'notice_days_before_due' : $key)], ['valor' => (string) $value]);
        }
        return response()->json(['message' => 'Reglas de avisos guardadas.', 'data' => $data]);
    }

    public function branches()
    {
        return response()->json(['data' => SaasBranchIntegration::with('cliente:id,razon_social,nombre_comercial,ruc')->orderBy('branch_name')->get()]);
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'external_branch_id' => ['required', 'string', 'max:100', Rule::unique('saas_branch_integrations')->where('provider', 'hotelhub')],
            'branch_name' => ['required', 'string', 'max:255'],
        ]);
        $branch = SaasBranchIntegration::create($data + ['provider' => 'hotelhub']);
        return response()->json(['data' => $branch->load('cliente')], 201);
    }

    public function setBranchStatus(Request $request, SaasBranchIntegration $branch)
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        if ($branch->provider !== 'hotelhub') {
            return response()->json(['message' => 'Proveedor no soportado.'], 422);
        }

        $url = rtrim((string) config('services.hotelhub.base_url'), '/').'/api/integrations/erp/branches/'.rawurlencode($branch->external_branch_id).'/status';
        $secret = (string) config('services.hotelhub.integration_secret');
        if (!$url || $secret === '' || !config('services.hotelhub.base_url')) {
            return response()->json(['message' => 'Falta configurar HOTELHUB_BASE_URL o HOTELHUB_INTEGRATION_SECRET en el ERP.'], 503);
        }
        $payload = ['active' => (bool) $data['active']];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp."\n".$body, $secret);
        try {
            $response = Http::timeout(12)->withHeaders([
                'X-SaaS-Timestamp' => $timestamp,
                'X-SaaS-Signature' => $signature,
            ])->withBody($body, 'application/json')->patch($url);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo conectar con HotelHUB; no se cambió el estado local.'], 502);
        }
        if (!$response->successful()) {
            return response()->json(['message' => 'HotelHUB rechazó el cambio de estado.', 'provider_status' => $response->status(), 'provider_response' => $response->json()], 502);
        }
        $branch->update(['active' => $payload['active'], 'last_synced_at' => now()]);
        return response()->json(['message' => 'Estado sincronizado con HotelHUB.', 'data' => $branch->fresh()]);
    }

    public function notices(Request $request)
    {
        $data = $request->validate(['branch_id' => ['required', 'string', 'max:100']]);
        $branch = SaasBranchIntegration::where('provider', 'hotelhub')->where('external_branch_id', $data['branch_id'])->with('cliente')->first();
        if (!$branch) return response()->json(['notices' => []]);

        $today = Carbon::today();
        $before = (int) (Configuracion::where('clave', 'saas_notice_days_before_due')->value('valor') ?? 3);
        $alertDays = (int) (Configuracion::where('clave', 'saas_suspension_alert_days')->value('valor') ?? 7);
        $cuotas = Cuota::with(['contrato'])
            ->whereHas('contrato', fn ($query) => $query->where('cliente_id', $branch->cliente_id)->where('tipo_contrato', 'saas')->where(fn ($state) => $state->whereNull('estado')->orWhereNotIn('estado', ['anulado', 'cancelado'])))
            ->whereNull('deleted_at')->where('situacion', '!=', 'pagado')->get();

        $notices = [];
        foreach ($cuotas as $cuota) {
            $paid = (float) PagosCuotum::where('cuota_id', $cuota->id)->whereNull('deleted_at')->where(function ($q) {
                $q->whereNull('estado_revision')->orWhere('estado_revision', 'aprobado');
            })->sum('monto_pagado');
            $balance = max(0, round((float) $cuota->monto - $paid, 2));
            if ($balance <= 0.009) continue;
            $due = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();
            $days = $today->diffInDays($due, false);
            $base = ['quota_id' => $cuota->id, 'due_date' => $due->toDateString(), 'balance' => $balance, 'currency' => 'PEN'];
            if ($days >= 0 && $days <= $before) {
                $notices[] = $base + ['type' => 'due_soon', 'message' => $days === 0 ? 'Tu cuota vence hoy. Por favor, registra tu pago.' : "Tu cuota vence en {$days} días. Por favor, programa tu pago."];
            } elseif ($days < 0) {
                $late = abs($days);
                $notices[] = $base + ['type' => $late >= $alertDays ? 'suspension_today' : 'overdue', 'message' => $late >= $alertDays
                    ? 'Tu cuota sigue pendiente y hoy se revisará la suspensión del servicio. Registra tu pago para evitar el corte.'
                    : "Tu cuota venció hace {$late} días. El servicio podría suspenderse si no se registra el pago."];
            }
        }
        return response()->json(['notices' => $notices]);
    }

    public function alerts()
    {
        $alertDays = (int) (Configuracion::where('clave', 'saas_suspension_alert_days')->value('valor') ?? 7);
        $today = Carbon::today();
        $branches = SaasBranchIntegration::with('cliente')->where('provider', 'hotelhub')->where('active', true)->get();
        $alerts = [];
        foreach ($branches as $branch) {
            $due = Cuota::with('contrato')->whereHas('contrato', fn ($q) => $q->where('cliente_id', $branch->cliente_id)->where('tipo_contrato', 'saas')->where(fn ($state) => $state->whereNull('estado')->orWhereNotIn('estado', ['anulado', 'cancelado'])))
                ->whereNull('deleted_at')->where('situacion', '!=', 'pagado')->whereDate('fecha_vencimiento', '<=', $today->copy()->subDays($alertDays))->get();
            $outstanding = $due->filter(function ($quota) {
                $paid = (float) PagosCuotum::where('cuota_id', $quota->id)->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('estado_revision')->orWhere('estado_revision', 'aprobado'))->sum('monto_pagado');
                return round((float) $quota->monto - $paid, 2) > 0.009;
            });
            if ($outstanding->isEmpty()) continue;
            $alerts[] = [
                'branch_id' => $branch->id,
                'cliente' => $branch->cliente?->razon_social ?? $branch->cliente?->nombre_comercial,
                'branch_name' => $branch->branch_name,
                'external_branch_id' => $branch->external_branch_id,
                'overdue_quotas' => $outstanding->count(),
                'days_threshold' => $alertDays,
                'message' => 'Revisar pago y decidir si se suspende esta sucursal.',
            ];
        }
        return response()->json(['data' => $alerts]);
    }
}
