<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoProductoModulo;
use App\Models\Cuota;
use App\Models\Modulo;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportService
{
    public const TYPES = [
        'contratos_estado',
        'situacion_cuotas',
        'contratos_servicio',
        'contratos_modulo',
        'listado_clientes',
        'fechas_contrato',
    ];

    public function options(): array
    {
        return [
            'productos' => Producto::query()->orderBy('nombre')->get(['id', 'nombre']),
            'modulos' => Modulo::query()->orderBy('nombre')->get(['id', 'nombre', 'producto_id']),
            'servicios' => Contrato::query()->whereNotNull('tipo_contrato')->distinct()->orderBy('tipo_contrato')->pluck('tipo_contrato')->values(),
            'estados_contrato' => Contrato::query()->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado')->values(),
            'situaciones_cuota' => ['pagado', 'pendiente', 'vencido'],
        ];
    }

    public function make(string $type, Request $request): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404, 'El reporte solicitado no existe.');

        return match ($type) {
            'contratos_estado' => $this->contractsStatus($request),
            'situacion_cuotas' => $this->installments($request),
            'contratos_servicio' => $this->contractsByService($request),
            'contratos_modulo' => $this->contractsByModule($request),
            'listado_clientes' => $this->clients($request),
            'fechas_contrato' => $this->contractsByDate($request),
        };
    }

    private function contractsStatus(Request $request): array
    {
        $contracts = $this->contractQuery($request)->with('cliente')->orderByDesc('created_at')->get();
        $rows = $contracts->map(fn (Contrato $contract) => [
            $contract->numero,
            $this->clientName($contract->cliente),
            $contract->cliente?->ruc,
            $this->label($contract->tipo_contrato),
            optional($contract->created_at)->format('d/m/Y'),
            optional($contract->fecha_inicio)->format('d/m/Y'),
            optional($contract->fecha_fin)->format('d/m/Y'),
            $this->label($contract->estado ?: 'activo'),
            (float) $contract->total,
        ])->values();

        return $this->result(
            'Contratos por estado y situación',
            'Detalle contractual con estado, vigencia, servicio e importe.',
            ['Contrato', 'Cliente', 'RUC', 'Servicio', 'Emisión', 'Inicio', 'Vencimiento', 'Estado', 'Total (S/)'],
            $rows,
            ['cantidad' => $contracts->count(), 'total' => (float) $contracts->sum('total')],
            [8]
        );
    }

    private function installments(Request $request): array
    {
        $query = Cuota::query()->with(['contrato.cliente'])->withSum('pagos_cuota as pagado', 'monto_pagado');
        $this->applyContractRelationFilters($query, $request);
        $this->applyDateRange($query, $request, 'fecha_vencimiento');

        if ($request->filled('situacion')) {
            $situation = $request->string('situacion')->toString();
            if ($situation === 'vencido') {
                $query->where('situacion', '!=', 'pagado')->whereDate('fecha_vencimiento', '<', today());
            } elseif ($situation === 'pendiente') {
                $query->where('situacion', '!=', 'pagado')->whereDate('fecha_vencimiento', '>=', today());
            } else {
                $query->where('situacion', $situation);
            }
        }

        $installments = $query->orderBy('fecha_vencimiento')->get();
        $rows = $installments->map(function (Cuota $installment) {
            $paid = min((float) ($installment->pagado ?? 0), (float) $installment->monto);
            $debt = max((float) $installment->monto - $paid, 0);
            $situation = $debt <= 0 ? 'Pagado' : ($installment->fecha_vencimiento?->isPast() ? 'Vencido' : 'Pendiente');

            return [
                $installment->contrato?->numero,
                $this->clientName($installment->contrato?->cliente),
                optional($installment->fecha_vencimiento)->format('d/m/Y'),
                $situation,
                (float) $installment->monto,
                $paid,
                $debt,
                optional($installment->fecha_pago)->format('d/m/Y'),
            ];
        })->values();

        $totalDebt = (float) $rows->sum(fn (array $row) => $row[6]);

        return $this->result(
            'Situación de cuotas',
            'Cuotas pagadas y pendientes, deuda total y cantidad de cuotas en deuda.',
            ['Contrato', 'Cliente', 'Vencimiento', 'Situación', 'Cuota (S/)', 'Pagado (S/)', 'Deuda (S/)', 'Fecha de pago'],
            $rows,
            [
                'cantidad' => $installments->count(),
                'pagadas' => $rows->where('3', 'Pagado')->count(),
                'cuotas_deuda' => $rows->filter(fn (array $row) => $row[6] > 0)->count(),
                'total' => (float) $rows->sum(fn (array $row) => $row[4]),
                'pagado' => (float) $rows->sum(fn (array $row) => $row[5]),
                'deuda' => $totalDebt,
            ],
            [4, 5, 6]
        );
    }

    private function contractsByService(Request $request): array
    {
        $contracts = $this->contractQuery($request)->get();
        $groups = $contracts->groupBy(fn (Contrato $contract) => $contract->tipo_contrato ?: 'sin servicio');
        $rows = $groups->map(fn (Collection $items, string $service) => [
            $this->label($service),
            $items->count(),
            (float) $items->sum('total'),
            $items->unique('cliente_id')->count(),
        ])->sortByDesc(2)->values();

        return $this->result(
            'Contratos por servicio',
            'Cantidad e importe total en soles agrupados por servicio.',
            ['Servicio', 'Contratos', 'Total (S/)', 'Clientes'],
            $rows,
            ['cantidad' => $contracts->count(), 'total' => (float) $contracts->sum('total'), 'clientes' => $contracts->unique('cliente_id')->count()],
            [2]
        );
    }

    private function contractsByModule(Request $request): array
    {
        $query = ContratoProductoModulo::query()->with(['modulo', 'producto', 'contrato.cliente']);
        $this->applyPivotFilters($query, $request);
        $items = $query->get();
        $groups = $items->groupBy('modulo_id');
        $rows = $groups->map(function (Collection $group) {
            $first = $group->first();
            return [
                $first?->modulo?->nombre ?: 'Sin módulo',
                $first?->producto?->nombre ?: 'Sin producto',
                $group->unique('contrato_id')->count(),
                (float) $group->sum('precio'),
                $group->pluck('contrato.cliente_id')->filter()->unique()->count(),
            ];
        })->sortByDesc(3)->values();

        return $this->result(
            'Contratos por módulo',
            'Cantidad e importe contratado por módulo y producto.',
            ['Módulo', 'Producto', 'Contratos', 'Total (S/)', 'Clientes'],
            $rows,
            ['cantidad' => $items->pluck('contrato_id')->unique()->count(), 'modulos' => $groups->count(), 'total' => (float) $items->sum('precio')],
            [3]
        );
    }

    private function clients(Request $request): array
    {
        $query = Cliente::query()->with([
            'contratos.contratoProductoModulos.producto',
            'contratos.contratoProductoModulos.modulo',
            'contratos.cuotas.pagos_cuota',
        ]);

        if ($request->filled('buscar')) {
            $term = mb_strtolower($request->string('buscar')->toString());
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(razon_social) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(nombre_comercial) LIKE ?', ["%{$term}%"])
                    ->orWhere('ruc', 'like', "%{$term}%");
            });
        }
        if ($request->boolean('solo_deudores')) {
            $query->whereHas('contratos.cuotas', fn (Builder $q) => $q->where('situacion', '!=', 'pagado'));
        }
        $this->applyClientContractFilters($query, $request);

        $clients = $query->orderBy('razon_social')->get();
        $rows = $clients->map(function (Cliente $client) {
            $contracts = $client->contratos;
            $pivots = $contracts->flatMap->contratoProductoModulos;
            $debt = $contracts->flatMap->cuotas->sum(function (Cuota $installment) {
                $paid = (float) $installment->pagos_cuota->sum('monto_pagado');
                return max((float) $installment->monto - $paid, 0);
            });
            return [
                $client->ruc,
                $this->clientName($client),
                $client->dueno_celular ?: $client->representante_celular,
                $client->dueno_email ?: $client->representante_email,
                $contracts->pluck('tipo_contrato')->filter()->unique()->map(fn ($v) => $this->label($v))->implode(', '),
                $pivots->pluck('producto.nombre')->filter()->unique()->implode(', '),
                $pivots->pluck('modulo.nombre')->filter()->unique()->implode(', '),
                $contracts->count(),
                (float) $debt,
            ];
        });
        if ($request->boolean('solo_deudores')) {
            $rows = $rows->filter(fn (array $row) => $row[8] > 0);
        }
        $rows = $rows->values();

        return $this->result(
            'Listado de clientes',
            'Clientes clasificados por servicio, producto, módulo y condición de deuda.',
            ['RUC', 'Cliente', 'Teléfono', 'Correo', 'Servicios', 'Productos', 'Módulos', 'Contratos', 'Deuda (S/)'],
            $rows,
            ['cantidad' => $rows->count(), 'deudores' => $rows->filter(fn (array $row) => $row[8] > 0)->count(), 'deuda' => (float) $rows->sum(fn (array $row) => $row[8])],
            [8]
        );
    }

    private function contractsByDate(Request $request): array
    {
        $contracts = $this->contractQuery($request)->with('cliente')->orderBy($this->contractDateColumn($request))->get();
        $rows = $contracts->map(fn (Contrato $contract) => [
            $contract->numero,
            $this->clientName($contract->cliente),
            optional($contract->created_at)->format('d/m/Y'),
            optional($contract->fecha_inicio)->format('d/m/Y'),
            optional($contract->fecha_fin)->format('d/m/Y'),
            $this->label($contract->tipo_contrato),
            $this->label($contract->estado ?: 'activo'),
            (float) $contract->total,
        ])->values();

        return $this->result(
            'Contratos por fechas',
            'Contratos por fecha de emisión, inicio o vencimiento.',
            ['Contrato', 'Cliente', 'Emisión', 'Inicio', 'Vencimiento', 'Servicio', 'Estado', 'Total (S/)'],
            $rows,
            ['cantidad' => $contracts->count(), 'total' => (float) $contracts->sum('total')],
            [7]
        );
    }

    private function contractQuery(Request $request): Builder
    {
        $query = Contrato::query();
        if ($request->filled('estado')) $query->where('estado', $request->string('estado')->toString());
        if ($request->filled('servicio')) $query->where('tipo_contrato', $request->string('servicio')->toString());
        if ($request->filled('producto_id')) $query->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('producto_id', $request->integer('producto_id')));
        if ($request->filled('modulo_id')) $query->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('modulo_id', $request->integer('modulo_id')));
        if ($request->filled('buscar')) {
            $term = mb_strtolower($request->string('buscar')->toString());
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(numero) LIKE ?', ["%{$term}%"])
                    ->orWhereHas('cliente', fn (Builder $client) => $client->whereRaw('LOWER(razon_social) LIKE ?', ["%{$term}%"])->orWhere('ruc', 'like', "%{$term}%"));
            });
        }
        $this->applyDateRange($query, $request, $this->contractDateColumn($request));
        return $query;
    }

    private function contractDateColumn(Request $request): string
    {
        return match ($request->input('campo_fecha')) {
            'vencimiento' => 'fecha_fin',
            'inicio' => 'fecha_inicio',
            default => 'created_at',
        };
    }

    private function applyDateRange(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('fecha_desde')) $query->whereDate($column, '>=', $request->date('fecha_desde'));
        if ($request->filled('fecha_hasta')) $query->whereDate($column, '<=', $request->date('fecha_hasta'));
    }

    private function applyContractRelationFilters(Builder $query, Request $request): void
    {
        $query->whereHas('contrato', function (Builder $contract) use ($request) {
            if ($request->filled('estado')) $contract->where('estado', $request->string('estado')->toString());
            if ($request->filled('servicio')) $contract->where('tipo_contrato', $request->string('servicio')->toString());
            if ($request->filled('producto_id')) $contract->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('producto_id', $request->integer('producto_id')));
            if ($request->filled('modulo_id')) $contract->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('modulo_id', $request->integer('modulo_id')));
        });
    }

    private function applyPivotFilters(Builder $query, Request $request): void
    {
        if ($request->filled('producto_id')) $query->where('producto_id', $request->integer('producto_id'));
        if ($request->filled('modulo_id')) $query->where('modulo_id', $request->integer('modulo_id'));
        $query->whereHas('contrato', function (Builder $contract) use ($request) {
            if ($request->filled('estado')) $contract->where('estado', $request->string('estado')->toString());
            if ($request->filled('servicio')) $contract->where('tipo_contrato', $request->string('servicio')->toString());
            $this->applyDateRange($contract, $request, $this->contractDateColumn($request));
        });
    }

    private function applyClientContractFilters(Builder $query, Request $request): void
    {
        if (!$request->filled('servicio') && !$request->filled('producto_id') && !$request->filled('modulo_id') && !$request->filled('estado') && !$request->filled('fecha_desde') && !$request->filled('fecha_hasta')) return;
        $query->whereHas('contratos', function (Builder $contract) use ($request) {
            if ($request->filled('servicio')) $contract->where('tipo_contrato', $request->string('servicio')->toString());
            if ($request->filled('estado')) $contract->where('estado', $request->string('estado')->toString());
            if ($request->filled('producto_id')) $contract->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('producto_id', $request->integer('producto_id')));
            if ($request->filled('modulo_id')) $contract->whereHas('contratoProductoModulos', fn (Builder $q) => $q->where('modulo_id', $request->integer('modulo_id')));
            $this->applyDateRange($contract, $request, $this->contractDateColumn($request));
        });
    }

    private function result(string $title, string $description, array $columns, Collection $rows, array $summary, array $currencyColumns): array
    {
        return compact('title', 'description', 'columns', 'rows', 'summary', 'currencyColumns') + ['generated_at' => Carbon::now()->format('d/m/Y H:i')];
    }

    private function clientName(?Cliente $client): string
    {
        return $client?->razon_social ?: $client?->nombre_comercial ?: $client?->dueno_nombre ?: 'Sin cliente';
    }

    private function label(?string $value): string
    {
        return mb_convert_case(str_replace('_', ' ', (string) $value), MB_CASE_TITLE, 'UTF-8');
    }
}
