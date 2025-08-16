<?php

namespace App\Http\Controllers;

use App\Models\{Contrato, Cliente, ContratoProductoModulo};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ContratoResource;

class ContratoController extends Controller
{
    /**
     * Listar todos los contratos
     */

public function index(Request $request)
{
    $query = Contrato::with(['cliente', 'cuotas', 'contratoProductoModulos']);

    // Filtro de búsqueda
    if ($request->filled('search')) {
        $search = $request->get('search');

        $query->where(function ($q) use ($search) {
            $q->where('numero', 'ILIKE', "%{$search}%")
              ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
              ->orWhereHas('cliente', function ($q2) use ($search) {
                  $q2->where('razon_social', 'ILIKE', "%{$search}%")
                     ->orWhere('ruc', 'ILIKE', "%{$search}%")
                     ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%")
                     ->orWhere('representante_nombre', 'ILIKE', "%{$search}%");
              });
        });
    }

    $contratos = $query->paginate($request->get('per_page', 5));

    return response()->json([
        'data' => ContratoResource::collection($contratos->items()),
        'links' => [
            'first' => $contratos->url(1),
            'last' => $contratos->url($contratos->lastPage()),
            'prev' => $contratos->previousPageUrl(),
            'next' => $contratos->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $contratos->currentPage(),
            'from' => $contratos->firstItem(),
            'last_page' => $contratos->lastPage(),
            'path' => $contratos->path(),
            'per_page' => $contratos->perPage(),
            'to' => $contratos->lastItem(),
            'total' => $contratos->total(),
        ]
    ]);
}


    /**
     * Mostrar un contrato específico
     */
    public function show($id)
    {
        $contrato = Contrato::with(['cliente', 'cuotas', 'contratoProductoModulos'])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $contrato
        ], 200);
    }

    /**
     * Registrar un nuevo contrato
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), Contrato::$rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contrato = Contrato::create($request->all());

            // Registrar productos y módulos contratados
            if ($request->filled('productos_modulos')) {
                foreach ($request->productos_modulos as $pm) {
                    ContratoProductoModulo::create([
                        'contrato_id' => $contrato->id,
                        'producto_id' => $pm['producto_id'],
                        'modulo_id' => $pm['modulo_id'],
                        'precio' => $pm['precio']
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Contrato creado exitosamente',
                'data' => $contrato->load(['cliente', 'cuotas', 'contratoProductoModulos'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar el contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un contrato
     */
    public function update(Request $request, $id)
    {
        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), Contrato::$rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contrato->update($request->all());

            // Eliminar productos-módulos anteriores
            $contrato->contratoProductoModulos()->delete();

            // Registrar nuevos productos-módulos
            if ($request->filled('productos_modulos')) {
                foreach ($request->productos_modulos as $pm) {
                    ContratoProductoModulo::create([
                        'contrato_id' => $contrato->id,
                        'producto_id' => $pm['producto_id'],
                        'modulo_id' => $pm['modulo_id'],
                        'precio' => $pm['precio']
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Contrato actualizado correctamente',
                'data' => $contrato->load(['cliente', 'cuotas', 'contratoProductoModulos'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un contrato
     */
    public function destroy($id)
    {
        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        $contrato->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Contrato eliminado correctamente'
        ], 200);
    }
}
