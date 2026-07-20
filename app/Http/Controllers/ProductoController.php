<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    private function normalizeTipo(?string $tipo): string
    {
        return in_array($tipo, ['servicio', 'producto'], true) ? $tipo : 'servicio';
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $all = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $request->get('per_page', 5);

        $query = Producto::with([
            'modulos.contratos',
            'contratos',
            'avisos_saas',
        ])->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('tipo', 'ILIKE', "%{$search}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });

            $query->orWhereHas('modulos', function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_unitario', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_mensual', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_anual', 'ILIKE', "%{$search}%");
            });
        })->latest();

        if ($all) {
            return response()->json(
                ProductoResource::collection($query->get())
            );
        }

        $productos = $query->paginate($perPage);

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
            ],
        ]);
    }

    public function show($id)
    {
        $producto = Producto::with(['modulos', 'avisos_saas'])->find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => new ProductoResource($producto),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->productRules(), $this->productMessages());

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto = Producto::create([
                'nombre' => $request->input('nombre'),
                'tipo' => $this->normalizeTipo($request->input('tipo')),
                'descripcion' => $request->input('descripcion'),
            ]);

            foreach ($request->input('modulos', []) as $modulo) {
                $producto->modulos()->create($this->mapModuloPayload($modulo));
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Producto creado exitosamente',
                'data' => new ProductoResource($producto->load('modulos')),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->productRules(), $this->productMessages());

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto->update([
                'nombre' => $request->input('nombre'),
                'tipo' => $this->normalizeTipo($request->input('tipo')),
                'descripcion' => $request->input('descripcion'),
            ]);

            $producto->modulos()->delete();
            foreach ($request->input('modulos', []) as $modulo) {
                $producto->modulos()->create($this->mapModuloPayload($modulo));
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado correctamente',
                'data' => new ProductoResource($producto->load('modulos')),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Producto eliminado correctamente',
        ], 200);
    }

    private function productRules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:servicio,producto',
            'descripcion' => 'nullable|string',
            'modulos' => 'nullable|array',
            'modulos.*.nombre' => 'required|string|max:255',
            'modulos.*.descripcion_contrato' => 'nullable|string',
            'modulos.*.precio_mensual' => 'required|numeric|min:0',
            'modulos.*.precio_anual' => 'required|numeric|min:0',
        ];
    }

    private function productMessages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'tipo.required' => 'El tipo es obligatorio.',
            'modulos.*.nombre.required' => 'El nombre del concepto es obligatorio.',
            'modulos.*.precio_mensual.required' => 'El precio mensual del concepto es obligatorio.',
            'modulos.*.precio_mensual.numeric' => 'El precio mensual debe ser numerico.',
            'modulos.*.precio_anual.required' => 'El precio anual del concepto es obligatorio.',
            'modulos.*.precio_anual.numeric' => 'El precio anual debe ser numerico.',
        ];
    }

    private function mapModuloPayload(array $modulo): array
    {
        $precioMensual = (float) ($modulo['precio_mensual'] ?? 0);

        return [
            'nombre' => $modulo['nombre'],
            'descripcion_contrato' => $modulo['descripcion_contrato'] ?? null,
            'precio_unitario' => $precioMensual,
            'precio_mensual' => $precioMensual,
            'precio_anual' => (float) ($modulo['precio_anual'] ?? 0),
        ];
    }
}
