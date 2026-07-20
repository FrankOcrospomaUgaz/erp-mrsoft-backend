<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Facturador;
use App\Models\PagosCuotum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function resumen()
    {
        if (request()->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $next30Days = $today->copy()->addDays(30);
        $last6MonthsStart = $today->copy()->subMonths(5)->startOfMonth();

        $contratosActivosQuery = Contrato::query()
            ->whereNull('deleted_at')
            ->where('estado', '!=', 'anulado')
            ->whereDate('fecha_inicio', '<=', $today)
            ->whereDate('fecha_fin', '>=', $today);

        $ingresosMes = (float) PagosCuotum::query()
            ->whereBetween('fecha_pago', [$monthStart, $monthEnd])
            ->sum('monto_pagado');

        $facturadoMes = (float) Comprobante::query()
            ->whereBetween('fecha_emision', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total');

        $porCobrar = (float) Cuota::query()
            ->whereNull('deleted_at')
            ->where('situacion', '!=', 'pagado')
            ->sum('monto');

        $cuotasVencidas = Cuota::query()
            ->whereNull('deleted_at')
            ->where('situacion', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', $today)
            ->count();

        $contratosActivos = (clone $contratosActivosQuery)->count();

        $clientesActivos = (clone $contratosActivosQuery)
            ->distinct('cliente_id')
            ->count('cliente_id');

        $comprobantesPendientes = Comprobante::query()
            ->whereIn('estado', ['E', 'R'])
            ->count();

        $comprobantesConError = Comprobante::query()
            ->whereIn('estado', ['X', 'I', 'V'])
            ->count();

        $facturadorActivo = Facturador::query()
            ->where('activo', true)
            ->latest()
            ->first() ?? Facturador::latest()->first();

        $facturadorConfigured = $facturadorActivo
            && filled($facturadorActivo->empresa_id)
            && filled($facturadorActivo->ruc)
            && filled($facturadorActivo->razon_social)
            && filled($facturadorActivo->usuario_sol)
            && filled($facturadorActivo->clave_sol);

        $recaudacionMensual = PagosCuotum::query()
            ->selectRaw("DATE_TRUNC('month', fecha_pago) as periodo")
            ->selectRaw('SUM(monto_pagado) as total')
            ->where('fecha_pago', '>=', $last6MonthsStart)
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->map(function ($item) {
                $periodo = Carbon::parse($item->periodo);

                return [
                    'periodo' => $periodo->format('Y-m'),
                    'label' => $periodo->translatedFormat('M Y'),
                    'total' => (float) $item->total,
                ];
            });

        $facturacionMensual = Comprobante::query()
            ->selectRaw("DATE_TRUNC('month', fecha_emision) as periodo")
            ->selectRaw('SUM(total) as total')
            ->where('fecha_emision', '>=', $last6MonthsStart->toDateString())
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->map(function ($item) {
                $periodo = Carbon::parse($item->periodo);

                return [
                    'periodo' => $periodo->format('Y-m'),
                    'label' => $periodo->translatedFormat('M Y'),
                    'total' => (float) $item->total,
                ];
            });

        $estadoComprobantes = Comprobante::query()
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->map(fn ($item) => [
                'estado' => $item->estado,
                'total' => (int) $item->total,
            ]);

        $proximasCuotas = Cuota::query()
            ->with(['contrato.cliente'])
            ->whereNull('deleted_at')
            ->where('situacion', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '>=', $today)
            ->whereDate('fecha_vencimiento', '<=', $next30Days)
            ->orderBy('fecha_vencimiento')
            ->limit(6)
            ->get()
            ->map(function (Cuota $cuota) {
                return [
                    'id' => $cuota->id,
                    'contrato_numero' => $cuota->contrato?->numero,
                    'cliente' => $cuota->contrato?->cliente?->razon_social
                        ?? $cuota->contrato?->cliente?->nombre_comercial
                        ?? $cuota->contrato?->cliente?->dueno_nombre,
                    'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                    'monto' => (float) $cuota->monto,
                    'situacion' => $cuota->situacion,
                ];
            });

        $topClientes = Comprobante::query()
            ->join('clientes', 'clientes.id', '=', 'comprobantes.cliente_id')
            ->whereNull('comprobantes.deleted_at')
            ->selectRaw("
                comprobantes.cliente_id,
                COALESCE(clientes.razon_social, clientes.nombre_comercial, clientes.dueno_nombre) as cliente,
                SUM(comprobantes.total) as total,
                COUNT(comprobantes.id) as comprobantes
            ")
            ->groupBy('comprobantes.cliente_id', 'cliente')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'cliente_id' => (int) $item->cliente_id,
                'cliente' => $item->cliente,
                'total' => (float) $item->total,
                'comprobantes' => (int) $item->comprobantes,
            ]);

        $tiposCliente = Cliente::query()
            ->whereNull('deleted_at')
            ->select('tipo')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tipo')
            ->get()
            ->map(fn ($item) => [
                'tipo' => $item->tipo === 'unico' ? 'local' : $item->tipo,
                'total' => (int) $item->total,
            ]);

        $tasaCobranza = $facturadoMes > 0 ? round(($ingresosMes / $facturadoMes) * 100, 2) : 0;

        return response()->json([
            'status' => 200,
            'data' => [
                'kpis' => [
                    'ingresos_mes' => round($ingresosMes, 2),
                    'facturado_mes' => round($facturadoMes, 2),
                    'por_cobrar' => round($porCobrar, 2),
                    'contratos_activos' => $contratosActivos,
                    'clientes_activos' => $clientesActivos,
                    'cuotas_vencidas' => $cuotasVencidas,
                    'comprobantes_pendientes' => $comprobantesPendientes,
                    'comprobantes_con_error' => $comprobantesConError,
                    'tasa_cobranza' => $tasaCobranza,
                ],
                'series' => [
                    'recaudacion_mensual' => $recaudacionMensual,
                    'facturacion_mensual' => $facturacionMensual,
                    'estado_comprobantes' => $estadoComprobantes,
                    'tipos_cliente' => $tiposCliente,
                ],
                'tablas' => [
                    'proximas_cuotas' => $proximasCuotas,
                    'top_clientes' => $topClientes,
                ],
                'facturador' => [
                    'configurado' => $facturadorConfigured,
                    'empresa_id' => $facturadorActivo?->empresa_id,
                    'modo' => $facturadorActivo?->modo,
                    'ruc' => $facturadorActivo?->ruc,
                    'razon_social' => $facturadorActivo?->razon_social,
                    'nombre_comercial' => $facturadorActivo?->nombre_comercial,
                    'activo' => (bool) ($facturadorActivo?->activo),
                ],
            ],
        ]);
    }
}
