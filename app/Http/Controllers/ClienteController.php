<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    /**
     * Listar todos los clientes
     */
    public function index()
    {
        $clientes = Cliente::with([
            'contactos_clientes',
            'contratos',
            'sucursales_clientes',
            'notificaciones',
            'avisos_saas'
        ])->get();

        return response()->json([
            'status' => 200,
            'data' => $clientes
        ], 200);
    }

    /**
     * Mostrar un cliente específico
     */
    public function show($id)
    {
        $cliente = Cliente::with([
            'contactos_clientes',
            'contratos',
            'sucursales_clientes',
            'notificaciones',
            'avisos_saas'
        ])->find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $cliente
        ], 200);
    }

    /**
     * Registrar un nuevo cliente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:natural,juridico',
            'ruc' => 'required|string|max:20|unique:clientes,ruc',
            'razon_social' => 'required|string|max:255',
            'dueno_nombre' => 'required|string|max:255',
            'dueno_celular' => 'nullable|string|max:20',
            'dueno_email' => 'nullable|email|max:255',
            'representante_nombre' => 'nullable|string|max:255',
            'representante_celular' => 'nullable|string|max:20',
            'representante_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente = Cliente::create($request->all());

        return response()->json([
            'status' => 201,
            'message' => 'Cliente creado exitosamente',
            'data' => $cliente
        ], 201);
    }

    /**
     * Actualizar un cliente
     */
    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:natural,juridico',
            'ruc' => 'required|string|max:20|unique:clientes,ruc,' . $id,
            'razon_social' => 'required|string|max:255',
            'dueno_nombre' => 'required|string|max:255',
            'dueno_celular' => 'nullable|string|max:20',
            'dueno_email' => 'nullable|email|max:255',
            'representante_nombre' => 'nullable|string|max:255',
            'representante_celular' => 'nullable|string|max:20',
            'representante_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Cliente actualizado correctamente',
            'data' => $cliente
        ], 200);
    }

    /**
     * Eliminar un cliente
     */
    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Cliente eliminado correctamente'
        ], 200);
    }
}
