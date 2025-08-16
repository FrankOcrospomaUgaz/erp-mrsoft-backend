<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
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
    $query = Cuota::with(['contrato.cliente']);

    // 🔎 Búsqueda global
    if ($request->filled('search')) {
        $search = $request->get('search');

        $query->where(function ($q) use ($search) {
            $q->where('situacion', 'ILIKE', "%{$search}%")
              ->orWhere('monto', '::text ILIKE', "%{$search}%") // buscar monto como texto
              ->orWhereHas('contrato', function ($q2) use ($search) {
                  $q2->where('numero', 'ILIKE', "%{$search}%")
                     ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
                     ->orWhereHas('cliente', function ($q3) use ($search) {
                         $q3->where('razon_social', 'ILIKE', "%{$search}%")
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

    $cuotas = $query->paginate($request->get('per_page', 5));

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
        return response()->json([
            'status' => 200,
            'data' => $cuota->load(['contrato', 'pagos_cuota'])
        ], 200);
    }

    /**
     * Registrar una nueva cuota
     */
    public function store(Request $request)
    {
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
}
