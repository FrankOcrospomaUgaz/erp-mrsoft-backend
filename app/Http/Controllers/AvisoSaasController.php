<?php

namespace App\Http\Controllers;

use App\Models\AvisosSaa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvisoSaasController extends Controller
{
    public function index()
    {
        return response()->json(AvisosSaa::with(['cliente', 'producto'])->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'cliente_id' => 'required|exists:clientes,id',
            'producto_id' => 'required|exists:productos,id',
            'texto_aviso' => 'required|string',
            'tipo_aviso' => 'required|string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $aviso = AvisosSaa::create($request->all());

        return response()->json($aviso, 201);
    }

    public function show(AvisosSaa $aviso_saas)
    {
        return response()->json($aviso_saas->load(['cliente', 'producto']));
    }

    public function update(Request $request, AvisosSaa $aviso_saas)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date|after_or_equal:fecha_inicio',
            'cliente_id' => 'exists:clientes,id',
            'producto_id' => 'exists:productos,id',
            'texto_aviso' => 'string',
            'tipo_aviso' => 'string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $aviso_saas->update($request->all());

        return response()->json($aviso_saas);
    }

    public function destroy(AvisosSaa $aviso_saas)
    {
        $aviso_saas->delete();
        return response()->json(null, 204);
    }
}
