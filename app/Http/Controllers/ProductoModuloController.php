<?php
namespace App\Http\Controllers;

use App\Models\ContratoProductoModulo;
use Illuminate\Http\Request;

class ProductoModuloController extends Controller
{
    public function index()
    {
        return ContratoProductoModulo::with('contrato', 'producto', 'modulo')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'contrato_id' => 'required|integer',
            'producto_id' => 'required|integer',
            'modulo_id' => 'required|integer',
            'precio' => 'required|numeric'
        ]);
        return ContratoProductoModulo::create($request->all());
    }

    public function show($id)
    {
        return ContratoProductoModulo::with('contrato', 'producto', 'modulo')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $detalle = ContratoProductoModulo::findOrFail($id);
        $detalle->update($request->all());
        return $detalle;
    }

    public function destroy($id)
    {
        $detalle = ContratoProductoModulo::findOrFail($id);
        $detalle->delete();
        return response()->json(null, 204);
    }
}
