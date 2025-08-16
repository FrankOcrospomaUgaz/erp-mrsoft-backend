<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ProductoResource;

class ProductoController extends Controller
{
    /**
     * Listar todos los productos
     */
public function index(Request $request)
{
    $search = $request->get('search');
    $perPage = $request->get('per_page', 5);

    $productos = Producto::with([
        'modulos.contratos',
        'contratos',
        'avisos_saas'
    ])
    ->when($search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
            // Búsqueda en productos
            $q->where('nombre', 'ILIKE', "%{$search}%")
              ->orWhere('descripcion', 'ILIKE', "%{$search}%");
        });

        // Búsqueda en módulos relacionados
        $query->orWhereHas('modulos', function ($q) use ($search) {
            $q->where('nombre', 'ILIKE', "%{$search}%")
              ->orWhere('precio_unitario', 'ILIKE', "%{$search}%");
        });

        // Búsqueda en contratos relacionados al producto
        $query->orWhereHas('contratos', function ($q) use ($search) {
            $q->where('numero', 'ILIKE', "%{$search}%")
              ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
              ->orWhere('forma_pago', 'ILIKE', "%{$search}%")
              ->orWhere('total', 'ILIKE', "%{$search}%");
        });

        // Búsqueda en contratos relacionados a los módulos del producto
        $query->orWhereHas('modulos.contratos', function ($q) use ($search) {
            $q->where('numero', 'ILIKE', "%{$search}%")
              ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
              ->orWhere('forma_pago', 'ILIKE', "%{$search}%")
              ->orWhere('total', 'ILIKE', "%{$search}%");
        });
    })
    ->latest()
    ->paginate($perPage);

    return response()->json([
        'data' => ProductoResource::collection($productos->items()),
        'links' => [
            'first' => $productos->url(1),
            'last' => $productos->url($productos->lastPage()),
            'prev' => $productos->previousPageUrl(),
            'next' => $productos->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $productos->currentPage(),
            'from' => $productos->firstItem(),
            'last_page' => $productos->lastPage(),
            'path' => $productos->path(),
            'per_page' => $productos->perPage(),
            'to' => $productos->lastItem(),
            'total' => $productos->total(),
        ]
    ]);
}

    /**
     * Mostrar un producto específico
     */
    public function show($id)
    {
        $producto = Producto::with(['modulos', 'avisos_saas'])->find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $producto
        ], 200);
    }

    /**
     * Registrar un nuevo producto con módulos
     */
    public function store(Request $request)
    {
        $messages = [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'modulos.*.nombre.required' => 'El nombre del módulo es obligatorio.',
            'modulos.*.precio_unitario.required' => 'El precio unitario del módulo es obligatorio.',
            'modulos.*.precio_unitario.numeric' => 'El precio unitario debe ser numérico.'
        ];

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'modulos' => 'nullable|array',
            'modulos.*.nombre' => 'required|string|max:255',
            'modulos.*.precio_unitario' => 'required|numeric|min:0',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto = Producto::create($request->only(['nombre', 'descripcion']));

            if ($request->filled('modulos')) {
                foreach ($request->modulos as $modulo) {
                    $producto->modulos()->create($modulo);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Producto creado exitosamente',
                'data' => $producto->load('modulos')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un producto con módulos
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $messages = [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'modulos.*.nombre.required' => 'El nombre del módulo es obligatorio.',
            'modulos.*.precio_unitario.required' => 'El precio unitario del módulo es obligatorio.',
            'modulos.*.precio_unitario.numeric' => 'El precio unitario debe ser numérico.'
        ];

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'modulos' => 'nullable|array',
            'modulos.*.nombre' => 'required|string|max:255',
            'modulos.*.precio_unitario' => 'required|numeric|min:0',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto->update($request->only(['nombre', 'descripcion']));

            if ($request->has('modulos')) {
                $producto->modulos()->delete();
                foreach ($request->modulos as $modulo) {
                    $producto->modulos()->create($modulo);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado correctamente',
                'data' => $producto->load('modulos')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un producto
     */
    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Producto eliminado correctamente'
        ], 200);
    }
}
