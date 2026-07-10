<?php

namespace App\Http\Controllers;

use App\Models\Facturador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FacturadorController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Facturador::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $facturador = Facturador::create($validator->validated());

        return response()->json(['status' => 201, 'data' => $facturador], 201);
    }

    public function show($id)
    {
        $facturador = Facturador::find($id);

        if (!$facturador) {
            return response()->json(['status' => 404, 'message' => 'Facturador no encontrado'], 404);
        }

        return response()->json(['status' => 200, 'data' => $facturador]);
    }

    public function update(Request $request, $id)
    {
        $facturador = Facturador::find($id);

        if (!$facturador) {
            return response()->json(['status' => 404, 'message' => 'Facturador no encontrado'], 404);
        }

        $validator = $this->validator($request->all(), true);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $facturador->update($validator->validated());

        return response()->json(['status' => 200, 'data' => $facturador]);
    }

    public function destroy($id)
    {
        $facturador = Facturador::find($id);

        if (!$facturador) {
            return response()->json(['status' => 404, 'message' => 'Facturador no encontrado'], 404);
        }

        $facturador->delete();

        return response()->json(['status' => 200, 'message' => 'Facturador eliminado correctamente']);
    }

    private function validator(array $data, bool $updating = false)
    {
        $required = $updating ? 'sometimes' : 'required';

        return Validator::make($data, [
            'ruc' => ['nullable', 'string', 'max:20'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => [$required, 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'usuario_sol' => ['nullable', 'string', 'max:255'],
            'clave_sol' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'wsdl_factura' => ['nullable', 'url'],
            'wsdl_boleta' => ['nullable', 'url'],
            'wsdl_consulta' => ['nullable', 'url'],
            'wsdl_bajas' => ['nullable', 'url'],
            'modo' => ['nullable', Rule::in(['simulacion', 'produccion'])],
            'porcentaje_igv' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }
}
