<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratoController extends Controller
{
    /**
     * Listar todos los contratos
     */
    public function index()
    {
        $contratos = Contrato::with(['cliente', 'cuotas', 'contratoProductoModulos'])->get();

        return response()->json([
            'status' => 200,
            'data' => $contratos
        ], 200);
    }

    /**
     * Mostrar un contrato específico
     */
    public function show($id)
    {
        $contrato = Contrato::with(['cliente', 'cuotas', 'contratoProductoModulos'])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $contrato
        ], 200);
    }

    /**
     * Registrar un nuevo contrato
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), Contrato::$rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        $contrato = Contrato::create($request->all());

        return response()->json([
            'status' => 201,
            'message' => 'Contrato creado exitosamente',
            'data' => $contrato
        ], 201);
    }

    /**
     * Actualizar un contrato
     */
    public function update(Request $request, $id)
    {
        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), Contrato::$rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        $contrato->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Contrato actualizado correctamente',
            'data' => $contrato
        ], 200);
    }

    /**
     * Eliminar un contrato
     */
    public function destroy($id)
    {
        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        $contrato->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Contrato eliminado correctamente'
        ], 200);
    }
}
