<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CuotaController extends Controller
{
    /**
     * Listar todas las cuotas
     */
    public function index()
    {
        $cuotas = Cuota::with(['contrato'])->get();

        return response()->json([
            'status' => 200,
            'data' => $cuotas
        ], 200);
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
