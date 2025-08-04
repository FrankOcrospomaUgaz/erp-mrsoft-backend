<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Http\Resources\UsuarioResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{


public function index(Request $request)
{
    $usuarios = Usuario::with('tipos_usuario')->paginate($request->get('per_page', 5));

    return response()->json([
        'data' => UsuarioResource::collection($usuarios->items()),
        'links' => [
            'first' => $usuarios->url(1),
            'last' => $usuarios->url($usuarios->lastPage()),
            'prev' => $usuarios->previousPageUrl(),
            'next' => $usuarios->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $usuarios->currentPage(),
            'from' => $usuarios->firstItem(),
            'last_page' => $usuarios->lastPage(),
            'path' => $usuarios->path(),
            'per_page' => $usuarios->perPage(),
            'to' => $usuarios->lastItem(),
            'total' => $usuarios->total(),
        ]
    ]);
}


    public function store(Request $request)
    {
        $request->validate([
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'usuario' => 'required|string|unique:usuarios,usuario',
            'password' => 'required|string',
            'tipo_usuario_id' => 'required|integer'
        ]);

        $request['password'] = Hash::make($request->password);
        return Usuario::create($request->all());
    }

    public function show($id)
    {
        return Usuario::with('tipos_usuario')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $usuario = Usuario::findOrFail($id);
        if ($request->filled('password')) {
            $request['password'] = Hash::make($request->password);
        } else {
            unset($request['password']);
        }

        $usuario->update($request->all());
        return $usuario;
    }

    public function destroy($id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->delete();
        return response()->json(null, 204);
    }
}
