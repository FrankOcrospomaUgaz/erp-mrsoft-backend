<?php

namespace App\Http\Controllers;

use App\Models\TiposUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TiposUsuarioResource;

class TipoUsuarioController extends Controller
{
public function index(Request $request)
{
    $tiposUsuario = TiposUsuario::paginate($request->get('per_page', 5));

    return response()->json([
        'data' => TiposUsuarioResource::collection($tiposUsuario->items()),
        'links' => [
            'first' => $tiposUsuario->url(1),
            'last' => $tiposUsuario->url($tiposUsuario->lastPage()),
            'prev' => $tiposUsuario->previousPageUrl(),
            'next' => $tiposUsuario->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $tiposUsuario->currentPage(),
            'from' => $tiposUsuario->firstItem(),
            'last_page' => $tiposUsuario->lastPage(),
            'path' => $tiposUsuario->path(),
            'per_page' => $tiposUsuario->perPage(),
            'to' => $tiposUsuario->lastItem(),
            'total' => $tiposUsuario->total(),
        ]
    ]);
}
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:tipos_usuario,nombre',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no debe superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un tipo de usuario con ese nombre.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tipoUsuario = TiposUsuario::create([
                'nombre' => $request->nombre,
            ]);

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Tipo de usuario creado exitosamente',
                'data' => $tipoUsuario
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el tipo de usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $tipo = TiposUsuario::find($id);

        if (!$tipo) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Detalle del tipo de usuario',
            'data' => $tipo
        ]);
    }

    public function update(Request $request, $id)
    {
        $tipo = TiposUsuario::find($id);

        if (!$tipo) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de usuario no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:tipos_usuario,nombre,' . $id,
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no debe superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un tipo de usuario con ese nombre.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tipo->update([
                'nombre' => $request->nombre,
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Tipo de usuario actualizado exitosamente',
                'data' => $tipo
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el tipo de usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $tipo = TiposUsuario::find($id);

        if (!$tipo) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de usuario no encontrado'
            ], 404);
        }

        try {
            $tipo->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Tipo de usuario eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el tipo de usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
