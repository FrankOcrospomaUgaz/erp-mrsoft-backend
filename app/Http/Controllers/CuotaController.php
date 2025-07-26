<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CuotaController extends Controller
{
    public function index()
    {
        return response()->json(Cuota::with(['contrato'])->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrato_id' => 'required|exists:contratos,id',
            'monto' => 'required|numeric',
            'fecha_vencimiento' => 'required|date',
            'fecha_pago' => 'nullable|date',
            'situacion' => 'required|string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $cuota = Cuota::create($request->all());

        return response()->json($cuota, 201);
    }

    public function show(Cuota $cuota)
    {
        return response()->json($cuota->load(['contrato', 'pagos_cuota']));
    }

    public function update(Request $request, Cuota $cuota)
    {
        $validator = Validator::make($request->all(), [
            'contrato_id' => 'exists:contratos,id',
            'monto' => 'numeric',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'nullable|date',
            'situacion' => 'string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $cuota->update($request->all());

        return response()->json($cuota);
    }

    public function destroy(Cuota $cuota)
    {
        $cuota->delete();
        return response()->json(null, 204);
    }
}
