<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SucursalesCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SucursalesClienteController extends Controller
{
    public function index()
    {
        return response()->json(SucursalesCliente::with('cliente')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'nombre'     => 'required|string|max:255',
            'direccion'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sucursal = SucursalesCliente::create($request->all());

        return response()->json(['message' => 'Sucursal registrada correctamente', 'data' => $sucursal]);
    }

    public function show($id)
    {
        $sucursal = SucursalesCliente::with('cliente')->findOrFail($id);
        return response()->json($sucursal);
    }

    public function update(Request $request, $id)
    {
        $sucursal = SucursalesCliente::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'nombre'     => 'required|string|max:255',
            'direccion'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sucursal->update($request->all());

        return response()->json(['message' => 'Sucursal actualizada correctamente', 'data' => $sucursal]);
    }

    public function destroy($id)
    {
        $sucursal = SucursalesCliente::findOrFail($id);
        $sucursal->delete();

        return response()->json(['message' => 'Sucursal eliminada correctamente']);
    }
}
