<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MensajeController extends Controller
{
    public function index()
    {
        return response()->json(Mensaje::all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'detalle' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mensaje = Mensaje::create($request->all());

        return response()->json(['message' => 'Mensaje creado correctamente', 'data' => $mensaje], 201);
    }

    public function show(Mensaje $mensaje)
    {
        return response()->json($mensaje);
    }

    public function update(Request $request, Mensaje $mensaje)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'detalle' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mensaje->update($request->all());

        return response()->json(['message' => 'Mensaje actualizado correctamente', 'data' => $mensaje]);
    }

    public function destroy(Mensaje $mensaje)
    {
        $mensaje->delete();
        return response()->json(['message' => 'Mensaje eliminado correctamente']);
    }
}
