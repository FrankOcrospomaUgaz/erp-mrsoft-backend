<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ContactosCliente;
use App\Models\SucursalesCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ClienteResource;
class ClienteController extends Controller
{
    /**
     * Listar todos los clientes
     */


public function index(Request $request)
{
    $search = $request->get('search');
    $perPage = $request->get('per_page', 5);

    $clientes = Cliente::with([
        'contactos_clientes',
        'contratos',
        'sucursales_clientes',
        'avisos_saas'
    ])
    ->when($search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
            // Búsqueda en tabla clientes
            $q->where('ruc', 'ILIKE', "%{$search}%")
              ->orWhere('razon_social', 'ILIKE', "%{$search}%")
              ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%")
              ->orWhere('dueno_celular', 'ILIKE', "%{$search}%")
              ->orWhere('dueno_email', 'ILIKE', "%{$search}%")
              ->orWhere('representante_nombre', 'ILIKE', "%{$search}%")
              ->orWhere('representante_celular', 'ILIKE', "%{$search}%")
              ->orWhere('representante_email', 'ILIKE', "%{$search}%");
        });

        // Búsqueda en contactos relacionados
        $query->orWhereHas('contactos_clientes', function ($q) use ($search) {
            $q->where('nombre', 'ILIKE', "%{$search}%")
              ->orWhere('celular', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%");
        });
    })
    ->paginate($perPage);

    return response()->json([
        'data' => ClienteResource::collection($clientes->items()),
        'links' => [
            'first' => $clientes->url(1),
            'last' => $clientes->url($clientes->lastPage()),
            'prev' => $clientes->previousPageUrl(),
            'next' => $clientes->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $clientes->currentPage(),
            'from' => $clientes->firstItem(),
            'last_page' => $clientes->lastPage(),
            'path' => $clientes->path(),
            'per_page' => $clientes->perPage(),
            'to' => $clientes->lastItem(),
            'total' => $clientes->total(),
        ]
    ]);
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
     * Registrar un nuevo cliente con transacciones
     */
    public function store(Request $request)
    {
        $messages = [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser "corporacion" o "unico".',
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.unique' => 'Ya existe un cliente con este RUC.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'dueno_nombre.required' => 'El nombre del dueño es obligatorio.',
        ];

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:corporacion,unico',
            'ruc' => 'required|string|max:20|unique:clientes,ruc',
            'razon_social' => 'required|string|max:255',
            'dueno_nombre' => 'required|string|max:255',
            'dueno_celular' => 'nullable|string|max:20',
            'dueno_email' => 'nullable|email|max:255',
            'representante_nombre' => 'nullable|string|max:255',
            'representante_celular' => 'nullable|string|max:20',
            'representante_email' => 'nullable|email|max:255',
            'contactos' => 'nullable|array',
            'contactos.*.nombre' => 'required|string',
            'contactos.*.celular' => 'required|string',
            'contactos.*.email' => 'required|email',
            'sucursales' => 'nullable|array',
            'sucursales.*.nombre' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cliente = Cliente::create($request->only([
                'tipo',
                'ruc',
                'razon_social',
                'dueno_nombre',
                'dueno_celular',
                'dueno_email',
                'representante_nombre',
                'representante_celular',
                'representante_email'
            ]));

            if ($request->has('contactos')) {
                foreach ($request->contactos as $contacto) {
                    $cliente->contactos_clientes()->create($contacto);
                }
            }

            if ($request->tipo === 'corporacion' && $request->has('sucursales')) {
                foreach ($request->sucursales as $sucursal) {
                    $cliente->sucursales_clientes()->create($sucursal);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Cliente creado exitosamente',
                'data' => $cliente->load('contactos_clientes', 'sucursales_clientes')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un cliente (sin transacción aún)
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

        $messages = [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser "corporacion" o "unico".',
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.unique' => 'Ya existe un cliente con este RUC.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'dueno_nombre.required' => 'El nombre del dueño es obligatorio.',
        ];

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:corporacion,unico',
            'ruc' => 'required|string|max:20|unique:clientes,ruc,' . $id,
            'razon_social' => 'required|string|max:255',
            'dueno_nombre' => 'required|string|max:255',
            'dueno_celular' => 'nullable|string|max:20',
            'dueno_email' => 'nullable|email|max:255',
            'representante_nombre' => 'nullable|string|max:255',
            'representante_celular' => 'nullable|string|max:20',
            'representante_email' => 'nullable|email|max:255',
            'contactos' => 'nullable|array',
            'contactos.*.nombre' => 'required|string',
            'contactos.*.celular' => 'required|string',
            'contactos.*.email' => 'required|email',
            'sucursales' => 'nullable|array',
            'sucursales.*.nombre' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cliente->update($request->only([
                'tipo',
                'ruc',
                'razon_social',
                'dueno_nombre',
                'dueno_celular',
                'dueno_email',
                'representante_nombre',
                'representante_celular',
                'representante_email'
            ]));

            // Eliminar contactos y volver a crearlos si se mandan nuevos
            if ($request->has('contactos')) {
                $cliente->contactos_clientes()->delete();
                foreach ($request->contactos as $contacto) {
                    $cliente->contactos_clientes()->create($contacto);
                }
            }

            // Eliminar y crear nuevas sucursales si es corporación
            if ($request->tipo === 'corporacion') {
                $cliente->sucursales_clientes()->delete();
                if ($request->has('sucursales')) {
                    foreach ($request->sucursales as $sucursal) {
                        $cliente->sucursales_clientes()->create($sucursal);
                    }
                }
            } else {
                // Si ya no es corporación, eliminar sucursales existentes
                $cliente->sucursales_clientes()->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Cliente actualizado correctamente',
                'data' => $cliente->load('contactos_clientes', 'sucursales_clientes')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
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

    public function sucursalesPorCliente($id)
    {
        $cliente = Cliente::with('sucursales_clientes')->find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'sucursales' => $cliente->sucursales_clientes
        ]);
    }
}
