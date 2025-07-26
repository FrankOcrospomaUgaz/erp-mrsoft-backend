<?php

namespace App\Http\Controllers;

use App\Models\TiposUsuario;
use Illuminate\Http\Request;

class TipoUsuarioController extends Controller
{
    public function index()
    {
        return TiposUsuario::all();
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:255']);
        return TiposUsuario::create($request->all());
    }

    public function show($id)
    {
        return TiposUsuario::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $tipo = TiposUsuario::findOrFail($id);
        $tipo->update($request->all());
        return $tipo;
    }

    public function destroy($id)
    {
        $tipo = TiposUsuario::findOrFail($id);
        $tipo->delete();
        return response()->json(null, 204);
    }
}
