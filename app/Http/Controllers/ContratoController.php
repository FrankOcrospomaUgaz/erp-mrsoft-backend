<?php

namespace App\Http\Controllers;

use App\Models\{Contrato, Cliente, ContratoProductoModulo, Cuota};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ContratoResource;

class ContratoController extends Controller
{
    /**
     * Listar todos los contratos
     */

    public function index(Request $request)
    {
        $query = Contrato::with([
            'cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ]);

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
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
            'numero'       => ['required', 'string', Rule::unique('contratos', 'numero')],
            'cliente_id'   => 'required|exists:clientes,id',
            'tipo_contrato' => 'required|string',
            'total'        => 'required|numeric|min:0',
            'forma_pago'   => 'required|string|in:unico,parcial',

            // (Opcional) valida estructura si llegan
            'productos_modulos'               => 'array',
            'productos_modulos.*.producto_id' => 'required_with:productos_modulos|exists:productos,id',
            'productos_modulos.*.modulo_id'   => 'required_with:productos_modulos|exists:modulos,id',
            'productos_modulos.*.precio'      => 'required_with:productos_modulos|numeric|min:0',

            'cuotas'                          => 'array',
            'cuotas.*.monto'                  => 'required_with:cuotas|numeric|min:0.01',
            'cuotas.*.fecha_vencimiento'      => 'required_with:cuotas|date',
        ], [
            'forma_pago.in' => 'La forma de pago debe ser unico o parcial.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $contrato = Contrato::create($request->only([
                'fecha_inicio',
                'fecha_fin',
                'numero',
                'cliente_id',
                'tipo_contrato',
                'total',
                'forma_pago'
            ]));

            // ----- Productos / Módulos -----
            $productosModulos = collect($request->input('productos_modulos', []))
                ->filter(fn($pm) => isset($pm['producto_id'], $pm['modulo_id'], $pm['precio']))
                ->map(fn($pm) => [
                    'producto_id' => (int)$pm['producto_id'],
                    'modulo_id'   => (int)$pm['modulo_id'],
                    'precio'      => (float)$pm['precio'],
                ])
                ->values()
                ->all();

            if (!empty($productosModulos)) {
                $contrato->contratoProductoModulos()->createMany($productosModulos);
            }

            // ----- Cuotas (solo si forma_pago = parcial) -----
            $cuotas = collect($request->input('cuotas', []))
                ->filter(fn($c) => isset($c['monto'], $c['fecha_vencimiento']))
                ->map(fn($c) => [
                    'monto'             => (float)$c['monto'],
                    'fecha_vencimiento' => $c['fecha_vencimiento'],
                    'situacion'         => $c['situacion'] ?? 'pendiente',
                ])
                ->values()
                ->all();

            if ($request->input('forma_pago') === 'parcial' && !empty($cuotas)) {
                $contrato->cuotas()->createMany($cuotas);
            }

            DB::commit();

            // Devuelve el contrato con todo cargado
            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status'  => 201,
                'message' => 'Contrato creado exitosamente',
                'data'    => new \App\Http\Resources\ContratoResource($contrato),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar el contrato',
                'error' => $e->getMessage(),
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
                'status'  => 404,
                'message' => 'Contrato no encontrado'
            ], 404);
        }

        // Reglas de validación para UPDATE (unique ignorando el ID)
        $rules = [
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin'    => 'sometimes|date|after:fecha_inicio',
            'numero'       => [
                'sometimes',
                'string',
                Rule::unique('contratos', 'numero')->ignore($contrato->id)
            ],
            'cliente_id'   => 'sometimes|exists:clientes,id',
            'tipo_contrato' => 'sometimes|string',
            'total'        => 'sometimes|numeric|min:0',
            'forma_pago'   => 'sometimes|string|in:unico,parcial',

            'productos_modulos'               => 'sometimes|array',
            'productos_modulos.*.producto_id' => 'required_with:productos_modulos|exists:productos,id',
            'productos_modulos.*.modulo_id'   => 'required_with:productos_modulos|exists:modulos,id',
            'productos_modulos.*.precio'      => 'required_with:productos_modulos|numeric|min:0',

            'cuotas'                     => 'sometimes|array',
            'cuotas.*.monto'             => 'required_with:cuotas|numeric|min:0.01',
            'cuotas.*.fecha_vencimiento' => 'required_with:cuotas|date',
            // opcionalmente podrías permitir enviar 'situacion' y 'fecha_pago'
            'cuotas.*.situacion'         => 'in:pendiente,pagado,vencido'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Actualiza solo campos permitidos
            $contrato->update($request->only([
                'fecha_inicio',
                'fecha_fin',
                'numero',
                'cliente_id',
                'tipo_contrato',
                'total',
                'forma_pago'
            ]));

            // -------- Productos / Módulos (reemplazo completo) --------
            $contrato->contratoProductoModulos()->delete(); // Soft delete
            $productosModulos = collect($request->input('productos_modulos', []))
                ->filter(fn($pm) => isset($pm['producto_id'], $pm['modulo_id'], $pm['precio']))
                ->map(fn($pm) => [
                    'producto_id' => (int)$pm['producto_id'],
                    'modulo_id'   => (int)$pm['modulo_id'],
                    'precio'      => (float)$pm['precio'],
                ])
                ->values()
                ->all();

            if (!empty($productosModulos)) {
                $contrato->contratoProductoModulos()->createMany($productosModulos);
            }

            // -------- Cuotas --------
            $formaPago = $request->input('forma_pago', $contrato->forma_pago);

            if ($formaPago === 'unico') {
                // Si pasó a pago único, eliminamos todas las cuotas
                $contrato->cuotas()->delete(); // Soft delete
            } else { // parcial
                if ($request->has('cuotas')) {
                    // Si enviaron cuotas, reemplazar todas
                    $contrato->cuotas()->delete();

                    $cuotas = collect($request->input('cuotas', []))
                        ->filter(fn($c) => isset($c['monto'], $c['fecha_vencimiento']))
                        ->map(fn($c) => [
                            'monto'             => (float)$c['monto'],
                            'fecha_vencimiento' => $c['fecha_vencimiento'],
                            'situacion'         => $c['situacion'] ?? 'pendiente',
                            'fecha_pago'        => $c['fecha_pago'] ?? null,
                        ])
                        ->values()
                        ->all();

                    if (!empty($cuotas)) {
                        $contrato->cuotas()->createMany($cuotas);
                    }
                }
                // Si no enviaron 'cuotas' y sigue siendo parcial, se mantienen las existentes.
            }

            DB::commit();

            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Contrato actualizado correctamente',
                'data'    => new \App\Http\Resources\ContratoResource($contrato),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 500,
                'message' => 'Error al actualizar el contrato',
                'error'   => $e->getMessage()
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

        DB::beginTransaction();

        try {
            // Marcar como eliminadas (soft delete) las relaciones
            $contrato->contratoProductoModulos()->each(function ($pm) {
                $pm->delete(); // SoftDelete
            });

            $contrato->cuotas()->each(function ($cuota) {
                $cuota->delete(); // SoftDelete
            });

            // Finalmente marcar como eliminado el contrato
            $contrato->delete(); // SoftDelete

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Contrato y sus relaciones eliminados correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
