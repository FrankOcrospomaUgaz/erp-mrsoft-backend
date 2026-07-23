<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContratoResource;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Facturador;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContratoController extends Controller
{
    public function siguienteNumero()
    {
        if (request()->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $year = now()->year;
        $pattern = '/^CT-' . $year . '-(\d+)$/';

        $lastSequence = Contrato::withTrashed()
            ->get(['numero'])
            ->reduce(function (int $carry, Contrato $contrato) use ($pattern) {
                if (!preg_match($pattern, (string) $contrato->numero, $matches)) {
                    return $carry;
                }

                return max($carry, (int) $matches[1]);
            }, 0);

        return response()->json([
            'status' => 200,
            'data' => [
                'numero' => sprintf('CT-%s-%03d', $year, $lastSequence + 1),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $clienteIds = $this->accessibleClienteIds($request);
        $query = Contrato::with([
            'cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->when($clienteIds, fn ($query) => $query->whereIn('cliente_id', $clienteIds));

        if ($request->filled('search')) {
            $search = $request->get('search');

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'ILIKE', "%{$search}%")
                    ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
                    ->orWhere('estado', 'ILIKE', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                            ->orWhere('ruc', 'ILIKE', "%{$search}%")
                            ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $contratos = $query->paginate($request->get('per_page', 5));

        return response()->json([
            'data' => ContratoResource::collection($contratos->items()),
            'links' => [
                'first' => $contratos->url(1),
                'last' => $contratos->url($contratos->lastPage()),
                'prev' => $contratos->previousPageUrl(),
                'next' => $contratos->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $contratos->currentPage(),
                'from' => $contratos->firstItem(),
                'last_page' => $contratos->lastPage(),
                'path' => $contratos->path(),
                'per_page' => $contratos->perPage(),
                'to' => $contratos->lastItem(),
                'total' => $contratos->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $contrato = Contrato::with([
            'cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (!$this->canAccessContrato($request, $contrato)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        return response()->json([
            'status' => 200,
            'data' => $contrato,
        ], 200);
    }

    public function pdf(Request $request, $id)
    {
        $contrato = Contrato::with([
            'cliente.parent_cliente.parent_cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (!$this->canAccessContrato($request, $contrato)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $cliente = $contrato->cliente;
        $jerarquia = $this->buildClientHierarchy($cliente);
        $facturador = Facturador::where('activo', true)->latest()->first() ?? Facturador::latest()->first();
        $modulosAgrupados = $contrato->contratoProductoModulos
            ->groupBy(fn($item) => $item->producto?->nombre ?? 'Servicio')
            ->map(function ($items, $producto) {
                return [
                    'producto' => $producto,
                    'items' => $items,
                    'subtotal' => $items->sum('precio'),
                ];
            })
            ->values();

        $firmaArrendador = $contrato->firma_arrendador ?? $facturador?->firma_arrendador_default ?? null;
        $firmaCliente = $contrato->firma_cliente ?? null;

        $data = [
            'contrato' => $contrato,
            'cliente' => $cliente,
            'jerarquia' => $jerarquia,
            'facturador' => $facturador,
            'modulosAgrupados' => $modulosAgrupados,
            'fechaEmision' => now(),
            'vigenciaDescripcion' => $this->resolveVigenciaDescription($contrato),
            'periodicidadDescripcion' => $contrato->periodicidad_cuota === 'anual' ? 'anual' : 'mensual',
            'formaPagoDescripcion' => $contrato->forma_pago === 'parcial' ? 'pago fraccionado' : 'pago unico',
            'tipoContratoDescripcion' => $this->resolveContractTypeDescription($contrato->tipo_contrato),
            'montoTotalTexto' => $this->formatMoney((float) $contrato->total),
            'montoTotalLetras' => $this->amountToWords((float) $contrato->total),
            'fechaInicioTexto' => $this->formatDateLong($contrato->fecha_inicio),
            'fechaFinTexto' => $this->formatDateLong($contrato->fecha_fin),
            'firmaArrendador' => $firmaArrendador,
            'firmaCliente' => $firmaCliente,
        ];

        $pdf = Pdf::loadView('pdf.contrato', $data)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-' . $contrato->numero . '.pdf"',
        ]);
    }

    public function guardarFirmas(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'firma_arrendador' => 'nullable|string',
            'firma_cliente' => 'nullable|string',
            'guardar_como_default_arrendador' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $contrato->update([
            'firma_arrendador' => array_key_exists('firma_arrendador', $validated) ? $validated['firma_arrendador'] : $contrato->firma_arrendador,
            'firma_cliente' => array_key_exists('firma_cliente', $validated) ? $validated['firma_cliente'] : $contrato->firma_cliente,
        ]);

        if (!empty($validated['guardar_como_default_arrendador']) && !empty($validated['firma_arrendador'])) {
            $facturador = Facturador::where('activo', true)->latest()->first() ?? Facturador::latest()->first();
            if ($facturador) {
                $facturador->update([
                    'firma_arrendador_default' => $validated['firma_arrendador'],
                ]);
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'Firmas registradas correctamente.',
            'data' => new ContratoResource($contrato->fresh()),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), $this->contractRules(), $this->contractMessages());
        $this->appendContractValidation($validator, $request);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $contrato = Contrato::create($this->extractContratoAttributes($request));

            $productosModulos = $this->mapProductosModulos($request->input('productos_modulos', []));
            if (!empty($productosModulos)) {
                $contrato->contratoProductoModulos()->createMany($productosModulos);
            }

            if ($request->input('forma_pago') === 'parcial') {
                $cuotas = $this->mapCuotas($request->input('cuotas', []));
                if (!empty($cuotas)) {
                    $contrato->cuotas()->createMany($cuotas);
                }
            }

            DB::commit();

            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Contrato creado exitosamente',
                'data' => new ContratoResource($contrato),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar el contrato',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->contractRules($contrato->id, true), $this->contractMessages());
        $this->appendContractValidation($validator, $request, true);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contrato->update($this->extractContratoAttributes($request, true));

            if ($request->has('productos_modulos')) {
                $contrato->contratoProductoModulos()->delete();
                $productosModulos = $this->mapProductosModulos($request->input('productos_modulos', []));
                if (!empty($productosModulos)) {
                    $contrato->contratoProductoModulos()->createMany($productosModulos);
                }
            }

            $formaPago = $request->input('forma_pago', $contrato->forma_pago);
            if ($formaPago === 'unico') {
                $contrato->cuotas()->delete();
            } elseif ($request->has('cuotas')) {
                $contrato->cuotas()->delete();
                $cuotas = $this->mapCuotas($request->input('cuotas', []));
                if (!empty($cuotas)) {
                    $contrato->cuotas()->createMany($cuotas);
                }
            }

            DB::commit();

            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Contrato actualizado correctamente',
                'data' => new ContratoResource($contrato),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el contrato',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if ($contrato->estado === 'anulado') {
            return response()->json([
                'status' => 422,
                'message' => 'El contrato ya se encuentra anulado.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'motivo_anulacion' => 'nullable|string',
            'fecha_anulacion' => 'required|date',
        ], [
            'fecha_anulacion.required' => 'La fecha de anulacion es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contrato->update([
            'estado' => 'anulado',
            'motivo_anulacion' => $request->input('motivo_anulacion'),
            'fecha_anulacion' => $request->input('fecha_anulacion'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Contrato anulado correctamente',
        ], 200);
    }

    private function contractRules(?int $contractId = null, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : 'required|';

        return [
            'fecha_inicio' => $prefix . 'date',
            'fecha_fin' => $prefix . 'date|after:fecha_inicio',
            'numero' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::unique('contratos', 'numero')->ignore($contractId),
            ],
            'cliente_id' => $prefix . 'exists:clientes,id',
            'tipo_contrato' => $prefix . 'string|in:desarrollo,saas,soporte',
            'vigencia_contrato' => $prefix . 'string|in:semestral,anual',
            'duracion_anios' => 'nullable|integer|min:1',
            'total' => $prefix . 'numeric|min:0',
            'forma_pago' => $prefix . 'string|in:unico,parcial',
            'periodicidad_cuota' => $prefix . 'string|in:mensual,anual',
            'productos_modulos' => 'nullable|array',
            'productos_modulos.*.producto_id' => 'required_with:productos_modulos|exists:productos,id',
            'productos_modulos.*.modulo_id' => 'required_with:productos_modulos|exists:modulos,id',
            'productos_modulos.*.precio' => 'required_with:productos_modulos|numeric|min:0',
            'cuotas' => 'nullable|array',
            'cuotas.*.monto' => 'required_with:cuotas|numeric|min:0.01',
            'cuotas.*.fecha_vencimiento' => 'required_with:cuotas|date',
            'cuotas.*.situacion' => 'nullable|in:pendiente,pagado,vencido',
        ];
    }

    private function contractMessages(): array
    {
        return [
            'forma_pago.in' => 'La forma de pago debe ser unico o parcial.',
            'vigencia_contrato.in' => 'La vigencia del contrato debe ser semestral o anual.',
            'periodicidad_cuota.in' => 'El tipo de pago solo puede ser mensual o anual.',
        ];
    }

    private function appendContractValidation($validator, Request $request, bool $partial = false): void
    {
        $validator->after(function ($validator) use ($request, $partial) {
            $tipoContrato = $request->input('tipo_contrato');
            $formaPago = $request->input('forma_pago');
            $productos = $request->input('productos_modulos', []);
            $cuotas = $request->input('cuotas', []);

            if ($tipoContrato === 'saas' && empty($productos)) {
                $validator->errors()->add('productos_modulos', 'Para contratos SaaS debe seleccionar al menos un producto.');
            }

            if (!$request->filled('periodicidad_cuota')) {
                $validator->errors()->add('periodicidad_cuota', 'Debe seleccionar el tipo de pago del contrato.');
            }

            if ($request->input('vigencia_contrato') === 'anual' && (int) $request->input('duracion_anios', 0) < 1) {
                $validator->errors()->add('duracion_anios', 'Debe indicar al menos 1 año de duración.');
            }

            if ($request->input('vigencia_contrato') === 'semestral' && $request->input('periodicidad_cuota') === 'anual') {
                $validator->errors()->add('periodicidad_cuota', 'Un contrato semestral no puede tener pago anual.');
            }

            if ($formaPago === 'parcial' && empty($cuotas) && !$partial) {
                $validator->errors()->add('cuotas', 'Debe registrar al menos una cuota para pago parcial.');
            }
        });
    }

    private function extractContratoAttributes(Request $request, bool $partial = false): array
    {
        $attributes = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'numero',
            'cliente_id',
            'tipo_contrato',
            'vigencia_contrato',
            'duracion_anios',
            'total',
            'forma_pago',
            'periodicidad_cuota',
        ]);

        if (($request->input('forma_pago') ?? null) === 'unico') {
            $attributes['periodicidad_cuota'] = $request->input('periodicidad_cuota');
        }

        if (($request->input('vigencia_contrato') ?? null) === 'semestral') {
            $attributes['duracion_anios'] = 1;
        }

        if (!$partial) {
            $attributes['estado'] = 'activo';
            $attributes['motivo_anulacion'] = null;
            $attributes['fecha_anulacion'] = null;
        }

        return $attributes;
    }

    private function mapProductosModulos(array $productosModulos): array
    {
        return collect($productosModulos)
            ->filter(fn($pm) => isset($pm['producto_id'], $pm['modulo_id'], $pm['precio']))
            ->map(fn($pm) => [
                'producto_id' => (int) $pm['producto_id'],
                'modulo_id' => (int) $pm['modulo_id'],
                'precio' => (float) $pm['precio'],
            ])
            ->values()
            ->all();
    }

    private function mapCuotas(array $cuotas): array
    {
        return collect($cuotas)
            ->filter(fn($c) => isset($c['monto'], $c['fecha_vencimiento']))
            ->map(fn($c) => [
                'monto' => (float) $c['monto'],
                'fecha_vencimiento' => $c['fecha_vencimiento'],
                'situacion' => $c['situacion'] ?? 'pendiente',
                'fecha_pago' => $c['fecha_pago'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function buildClientHierarchy(?Cliente $cliente): array
    {
        $linea = [];
        $cursor = $cliente;

        while ($cursor) {
            array_unshift($linea, $cursor);
            $cursor = $cursor->parent_cliente;
        }

        $root = $linea[0] ?? $cliente;
        $empresa = collect($linea)->first(fn($item) => $item->tipo === 'empresa');
        $local = collect($linea)->reverse()->first(fn($item) => $item->tipo === 'local') ?? $cliente;

        return [
            'linea' => $linea,
            'root' => $root,
            'empresa' => $empresa,
            'local' => $local,
        ];
    }

    private function resolveVigenciaDescription(Contrato $contrato): string
    {
        if ($contrato->vigencia_contrato === 'semestral') {
            return 'seis (6) meses';
        }

        $anios = max(1, (int) ($contrato->duracion_anios ?: 1));

        return $anios === 1
            ? 'un (1) ano'
            : $anios . ' anos';
    }

    private function resolveContractTypeDescription(string $tipo): string
    {
        return match ($tipo) {
            'desarrollo' => 'desarrollo de software',
            'soporte' => 'soporte tecnico',
            default => 'licenciamiento y alquiler de software SaaS',
        };
    }

    private function formatDateLong($date): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return $carbon->day . ' de ' . $meses[(int) $carbon->month] . ' de ' . $carbon->year;
    }

    private function formatMoney(float $amount): string
    {
        return 'S/ ' . number_format($amount, 2, '.', ',');
    }

    private function amountToWords(float $amount): string
    {
        $enteros = (int) floor($amount);
        $centimos = (int) round(($amount - $enteros) * 100);

        return strtoupper(trim($this->numberToSpanish($enteros))) . ' CON ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100 SOLES';
    }

    private function numberToSpanish(int $number): string
    {
        $units = [
            0 => 'cero', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro',
            5 => 'cinco', 6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve',
            10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce',
            15 => 'quince', 16 => 'dieciseis', 17 => 'diecisiete', 18 => 'dieciocho',
            19 => 'diecinueve', 20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidos',
            23 => 'veintitres', 24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiseis',
            27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve',
        ];

        $tens = [
            30 => 'treinta', 40 => 'cuarenta', 50 => 'cincuenta',
            60 => 'sesenta', 70 => 'setenta', 80 => 'ochenta', 90 => 'noventa',
        ];

        $hundreds = [
            100 => 'cien', 200 => 'doscientos', 300 => 'trescientos',
            400 => 'cuatrocientos', 500 => 'quinientos', 600 => 'seiscientos',
            700 => 'setecientos', 800 => 'ochocientos', 900 => 'novecientos',
        ];

        if ($number < 30) {
            return $units[$number];
        }

        if ($number < 100) {
            $base = intdiv($number, 10) * 10;
            $resto = $number % 10;
            return $resto === 0 ? $tens[$base] : $tens[$base] . ' y ' . $this->numberToSpanish($resto);
        }

        if ($number < 1000) {
            if ($number === 100) {
                return 'cien';
            }

            $base = intdiv($number, 100) * 100;
            $resto = $number % 100;
            $prefijo = $base === 100 ? 'ciento' : $hundreds[$base];
            return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
        }

        if ($number < 1000000) {
            $miles = intdiv($number, 1000);
            $resto = $number % 1000;
            $prefijo = $miles === 1 ? 'mil' : $this->numberToSpanish($miles) . ' mil';
            return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
        }

        $millones = intdiv($number, 1000000);
        $resto = $number % 1000000;
        $prefijo = $millones === 1 ? 'un millon' : $this->numberToSpanish($millones) . ' millones';

        return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
    }

    private function canAccessContrato(Request $request, Contrato $contrato): bool
    {
        $clienteIds = $this->accessibleClienteIds($request);

        return !$clienteIds || in_array((int) $contrato->cliente_id, $clienteIds, true);
    }

    private function accessibleClienteIds(Request $request): array
    {
        $clienteId = $request->user()?->cliente_id;

        if (!$clienteId) {
            return [];
        }

        $ids = [(int) $clienteId];
        $pending = [(int) $clienteId];

        while (!empty($pending)) {
            $children = Cliente::query()
                ->whereIn('parent_cliente_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $children)));
            $pending = $children;
        }

        return $ids;
    }
}
