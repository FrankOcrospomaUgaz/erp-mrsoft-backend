<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use App\Models\Cuota;
use App\Models\Cliente;
use App\Services\Facturacion\ComprobanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CuotaResource;
class CuotaController extends Controller
{
    /**
     * Listar todas las cuotas
     */

    public function index(Request $request)
    {
        $clienteIds = $this->accessibleClienteIds($request);
        $query = Cuota::with([
            'contrato.cliente',
            'contrato.contratoProductoModulos.producto',
            'contrato.contratoProductoModulos.modulo',
            'pagos_cuota',
            'comprobante.cliente.contactos_clientes',
            'comprobante.detalles',
            'comprobante.facturador',
        ])
            ->when($clienteIds, function ($query) use ($clienteIds) {
                $query->whereHas('contrato', fn ($contrato) => $contrato->whereIn('cliente_id', $clienteIds));
            });

        // 🔎 Búsqueda global
        if ($request->filled('search')) {
            $search = $request->get('search');

            $query->where(function ($q) use ($search) {
                $q->where('situacion', 'ILIKE', "%{$search}%")
                    ->orWhereRaw('CAST(monto AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('contrato', function ($q2) use ($search) {
                        $q2->where('numero', 'ILIKE', "%{$search}%")
                            ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
                            ->orWhereHas('cliente', function ($q3) use ($search) {
                                $q3->where('razon_social', 'ILIKE', "%{$search}%")
                                    ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                                    ->orWhere('ruc', 'ILIKE', "%{$search}%");
                            });
                    });
            });
        }

        // 📅 Filtros por fecha de vencimiento
        if ($request->filled('fecha_vencimiento_desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->get('fecha_vencimiento_desde'));
        }
        if ($request->filled('fecha_vencimiento_hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->get('fecha_vencimiento_hasta'));
        }

        // 📅 Filtros por fecha de pago
        if ($request->filled('fecha_pago_desde')) {
            $query->whereDate('fecha_pago', '>=', $request->get('fecha_pago_desde'));
        }
        if ($request->filled('fecha_pago_hasta')) {
            $query->whereDate('fecha_pago', '<=', $request->get('fecha_pago_hasta'));
        }

        // ⚡ Filtro por situacion
        if ($request->filled('situacion')) {
            $query->where('situacion', $request->get('situacion'));
        }

        // ⚡ Filtro por contrato específico
        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->get('contrato_id'));
        }

        // 🏢 Filtro por cliente específico
        if ($request->filled('cliente_id')) {
            $filterClienteId = (int) $request->get('cliente_id');
            $targetClienteIds = $this->getAllSubClientIds($filterClienteId);
            $query->whereHas('contrato', fn ($q) => $q->whereIn('cliente_id', $targetClienteIds));
        }

        // 📅 Ordenamiento por defecto: de la más próxima a vencer a la más lejana
        $query->orderBy('fecha_vencimiento', 'asc');

        $cuotas = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => CuotaResource::collection($cuotas->items()),
            'links' => [
                'first' => $cuotas->url(1),
                'last' => $cuotas->url($cuotas->lastPage()),
                'prev' => $cuotas->previousPageUrl(),
                'next' => $cuotas->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $cuotas->currentPage(),
                'from' => $cuotas->firstItem(),
                'last_page' => $cuotas->lastPage(),
                'path' => $cuotas->path(),
                'per_page' => $cuotas->perPage(),
                'to' => $cuotas->lastItem(),
                'total' => $cuotas->total(),
            ]
        ]);
    }

    /**
     * Mostrar una cuota específica
     */


    public function show(Cuota $cuota)
    {
        // Cargar todas las relaciones necesarias
        $cuota->load([
            'contrato.cliente',
            'contrato.contratoProductoModulos.producto',
            'contrato.contratoProductoModulos.modulo',
            'pagos_cuota',
            'comprobante.cliente.contactos_clientes',
            'comprobante.detalles',
            'comprobante.facturador',
        ]);

        if (!$this->canAccessCuota(request(), $cuota)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        return response()->json([
            'status' => 200,
            'data' => new CuotaResource($cuota)
        ], 200);
    }


    /**
     * Registrar una nueva cuota
     */
    public function store(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'contrato_id' => 'required|exists:contratos,id',
            'monto' => 'required|numeric',
            'fecha_vencimiento' => 'required|date',
            'fecha_pago' => 'nullable|date',
            'situacion' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cuota = Cuota::create($request->all());

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Cuota registrada exitosamente',
                'data' => $cuota->load(['contrato'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar la cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una cuota
     */
    public function update(Request $request, Cuota $cuota)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'contrato_id' => 'exists:contratos,id',
            'monto' => 'numeric',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'nullable|date',
            'situacion' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cuota->update($request->all());

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Cuota actualizada correctamente',
                'data' => $cuota->load(['contrato'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una cuota
     */
    public function destroy(Cuota $cuota)
    {
        if (request()->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        try {
            $cuota->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Cuota eliminada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar la cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reenviarFactura(Cuota $cuota, ComprobanteService $service)
    {
        if (request()->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $comprobante = Comprobante::where('cuota_id', $cuota->id)->latest()->first();

        if (!$comprobante) {
            return response()->json([
                'status' => 404,
                'message' => 'La cuota no tiene una factura asociada para reenviar.',
            ], 404);
        }

        try {
            $emitido = $service->emitir($comprobante);

            $status = $emitido->estado === 'X' ? 422 : 200;

            return response()->json([
                'status' => $status,
                'message' => $emitido->estado === 'X'
                    ? 'El facturador rechazo el reenvio: ' . $emitido->error_text
                    : 'Factura reenviada correctamente.',
                'data' => new ComprobanteResource($emitido),
            ], $status);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al reenviar la factura.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function siguienteCorrelativo(Request $request)
    {
        $tipoDocumento = $request->get('tipo_documento', 'F');
        $serie = strtoupper($request->get('serie', 'F001'));

        $ultimoCorrelativo = (int) Comprobante::where('tipo_documento', $tipoDocumento)
            ->where('serie', $serie)
            ->max('correlativo');

        $siguienteCorrelativo = $ultimoCorrelativo + 1;

        $facturador = Facturador::where('activo', true)->first() ?? Facturador::latest()->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'serie' => $serie,
                'ultimo_correlativo' => $ultimoCorrelativo,
                'siguiente_correlativo' => $siguienteCorrelativo,
                'facturador' => [
                    'id' => $facturador?->id,
                    'modo' => $facturador?->modo ?? 'simulacion',
                    'ruc' => $facturador?->ruc,
                    'razon_social' => $facturador?->razon_social,
                ]
            ]
        ]);
    }

    public function generarFactura(Request $request, Cuota $cuota, ComprobanteService $service)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $modo = $request->get('modo', 'sistema');

        if ($modo === 'manual') {
            return $this->generarFacturaManual($request, $cuota);
        }

        try {
            $resultado = DB::transaction(function () use ($request, $cuota, $service) {
                $cuotaBloqueada = Cuota::with('contrato.cliente')
                    ->lockForUpdate()
                    ->findOrFail($cuota->id);

                $existente = Comprobante::withTrashed()
                    ->where('cuota_id', $cuotaBloqueada->id)
                    ->first();

                if ($existente) {
                    return ['existente' => true, 'comprobante' => $existente];
                }

                $contrato = $cuotaBloqueada->contrato;
                $descripcion = sprintf(
                    'Cuota del contrato %s - vencimiento %s',
                    $contrato->numero,
                    optional($cuotaBloqueada->fecha_vencimiento)->format('d/m/Y')
                );

                $serie = strtoupper($request->get('serie', 'F001'));
                $correlativoSolicitado = $request->filled('correlativo') ? (int) $request->get('correlativo') : null;
                $fechaEmision = $request->get('fecha_emision') ?: null;

                $comprobante = $service->crear([
                    'cliente_id' => $contrato->cliente_id,
                    'contrato_id' => $contrato->id,
                    'cuota_id' => $cuotaBloqueada->id,
                    'tipo_documento' => 'F',
                    'serie' => $serie,
                    'correlativo' => $correlativoSolicitado,
                    'moneda' => 'PEN',
                    'forma_pago' => 'D',
                    'fecha_emision' => $fechaEmision,
                    'detalles' => [[
                        'descripcion' => $descripcion,
                        'cantidad' => 1,
                        'precio_unitario' => (float) $cuotaBloqueada->monto,
                        'tipo_igv' => '10',
                        'unidad' => 'NIU',
                    ]],
                ], false);

                return ['existente' => false, 'comprobante' => $comprobante];
            });

            if ($resultado['existente']) {
                return response()->json([
                    'status' => 409,
                    'message' => 'Esta cuenta por cobrar ya tiene una factura generada.',
                    'data' => new ComprobanteResource($resultado['comprobante']),
                ], 409);
            }

            $emitido = $service->emitir($resultado['comprobante']);

            return response()->json([
                'status' => $emitido->estado === 'X' ? 422 : 201,
                'message' => $emitido->estado === 'X'
                    ? 'La factura se genero, pero el facturador la rechazo: ' . $emitido->error_text . ' Puedes reenviar la misma factura.'
                    : 'Factura generada y enviada correctamente.',
                'data' => new ComprobanteResource($emitido),
            ], $emitido->estado === 'X' ? 422 : 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 500,
                'message' => 'No se pudo generar la factura: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generarFacturaManual(Request $request, Cuota $cuota)
    {
        $validator = Validator::make($request->all(), [
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'fecha_emision' => 'nullable|date',
            'monto_total' => 'nullable|numeric|min:0',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Revisa los datos ingresados para la factura manual.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comprobante = DB::transaction(function () use ($request, $cuota) {
                $existente = Comprobante::where('cuota_id', $cuota->id)->first();
                if ($existente) {
                    throw new \Exception('Esta cuota ya tiene una factura asignada.');
                }

                $cuota->load('contrato.cliente');
                $contrato = $cuota->contrato;

                $serie = strtoupper(trim($request->get('serie')));
                $correlativo = (int) $request->get('correlativo');
                $fechaEmision = $request->get('fecha_emision') ?: now()->toDateString();
                $total = (float) ($request->get('monto_total') ?: $cuota->monto);

                $facturador = Facturador::where('activo', true)->first() ?? Facturador::latest()->first();

                $pdfPath = null;
                if ($request->hasFile('pdf_file')) {
                    $file = $request->file('pdf_file');
                    $filename = sprintf('%s-%s-%s.pdf', $serie, str_pad((string)$correlativo, 6, '0', STR_PAD_LEFT), time());
                    $pdfPath = $file->storeAs('facturas_manuales', $filename, 'local');
                }

                $subtotal = round($total / 1.18, 2);
                $igv = round($total - $subtotal, 2);

                $comprobante = Comprobante::create([
                    'cliente_id' => $contrato->cliente_id,
                    'contrato_id' => $contrato->id,
                    'cuota_id' => $cuota->id,
                    'facturador_id' => $facturador?->id,
                    'tipo_documento' => 'F',
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                    'moneda' => 'PEN',
                    'forma_pago' => 'C',
                    'fecha_emision' => $fechaEmision,
                    'hora_emision' => now()->format('H:i:s'),
                    'subtotal' => $subtotal,
                    'igv' => $igv,
                    'total' => $total,
                    'estado' => 'M', // 'M' = Manual / Registrado
                    'pdf_path' => $pdfPath,
                ]);

                $comprobante->detalles()->create([
                    'descripcion' => sprintf('Factura manual cuota contrato %s', $contrato->numero),
                    'cantidad' => 1,
                    'precio_unitario' => $total,
                    'subtotal' => $subtotal,
                    'igv' => $igv,
                    'total' => $total,
                    'tipo_igv' => '10',
                    'unidad' => 'NIU',
                ]);

                return $comprobante;
            });

            return response()->json([
                'status' => 201,
                'message' => 'Factura manual cargada y vinculada exitosamente.',
                'data' => new ComprobanteResource($comprobante->load(['cliente', 'detalles'])),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function canAccessCuota(Request $request, Cuota $cuota): bool
    {
        $clienteIds = $this->accessibleClienteIds($request);

        return !$clienteIds || in_array((int) $cuota->contrato?->cliente_id, $clienteIds, true);
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

    private function getAllSubClientIds(int $clienteId): array
    {
        $ids = [$clienteId];
        $pending = [$clienteId];

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
