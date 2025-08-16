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
    $search = $request->get('search'); // palabra a buscar
    $perPage = $request->get('per_page', 5);

    $usuarios = Usuario::with('tipos_usuario')
        ->when($search, function ($query, $search) {
            return $query->where(function ($q) use ($search) {
                $q->where('nombres', 'ILIKE', "%{$search}%")
                  ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                  ->orWhere('usuario', 'ILIKE', "%{$search}%")
                  ->orWhereHas('tipos_usuario', function ($q2) use ($search) {
                      $q2->where('nombre', 'ILIKE', "%{$search}%");
                  });
            });
        })
        ->paginate($perPage);

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
