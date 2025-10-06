<?php

namespace App\Http\Controllers;

use App\Models\PagosCuotum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\PagosCuotumResource;
use App\Models\Cuota;

class PagoCuotumController extends Controller
{

    public function index(Request $request)
    {
        $query = PagosCuotum::with('cuota');

        // 🔍 Búsqueda por comprobante
        if ($request->filled('search')) {
            $query->where('comprobante', 'ILIKE', "%{$request->search}%");
        }

        // 📅 Filtrar por fecha de pago
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha_pago', [$request->fecha_inicio, $request->fecha_fin]);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_pago', '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_pago', '<=', $request->fecha_fin);
        }

        // 💰 Filtrar por rango de montos
        if ($request->filled('monto_min')) {
            $query->where('monto_pagado', '>=', $request->monto_min);
        }
        if ($request->filled('monto_max')) {
            $query->where('monto_pagado', '<=', $request->monto_max);
        }
        if ($request->filled('cuota_id')) {
            $query->where('cuota_id', '<=', $request->cuota_id);
        }

        // 📑 Paginación
        $pagos = $query->paginate($request->get('per_page', 5));

        return response()->json([
            'data' => PagosCuotumResource::collection($pagos->items()),
            'links' => [
                'first' => $pagos->url(1),
                'last' => $pagos->url($pagos->lastPage()),
                'prev' => $pagos->previousPageUrl(),
                'next' => $pagos->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $pagos->currentPage(),
                'from' => $pagos->firstItem(),
                'last_page' => $pagos->lastPage(),
                'path' => $pagos->path(),
                'per_page' => $pagos->perPage(),
                'to' => $pagos->lastItem(),
                'total' => $pagos->total(),
            ]
        ]);
    }

public function store(Request $request)
{
    $messages = [
        'cuota_id.required' => 'El campo cuota es obligatorio.',
        'fecha_pago.required' => 'La fecha de pago es obligatoria.',
        'monto_pagado.required' => 'El monto pagado es obligatorio.',
        'monto_pagado.numeric' => 'El monto pagado debe ser numérico.',
        'monto_pagado.min' => 'El monto pagado debe ser mayor a 0.',
        'comprobante.file' => 'El comprobante debe ser un archivo válido (imagen o PDF).',
    ];

    $validator = Validator::make($request->all(), [
        'cuota_id' => 'required|exists:cuotas,id',
        'fecha_pago' => 'required|date',
        'monto_pagado' => 'required|numeric|min:0.01',
        'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    ], $messages);

    if ($validator->fails()) {
        return response()->json([
            'status' => 422,
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        // Traer cuota y validar su estado
        $cuota = Cuota::findOrFail($request->cuota_id);

        if ($cuota->situacion === 'pagado') {
            return response()->json([
                'status' => 400,
                'message' => 'Esta cuota ya fue pagada completamente.'
            ], 400);
        }

        // Calcular suma de pagos previos
        $totalPagosPrevios = PagosCuotum::where('cuota_id', $cuota->id)->sum('monto_pagado');
        $nuevoTotal = $totalPagosPrevios + $request->monto_pagado;

        // Validar que no se exceda del monto de la cuota
        if ($nuevoTotal > $cuota->monto) {
            return response()->json([
                'status' => 400,
                'message' => 'El monto total pagado (' . number_format($nuevoTotal, 2) . ') no puede superar el monto de la cuota (' . number_format($cuota->monto, 2) . ').'
            ], 400);
        }

        // Guardar comprobante si se envía
        $rutaComprobante = null;
        if ($request->hasFile('comprobante')) {
            $rutaComprobante = $request->file('comprobante')->store('comprobantes', 'public');
        }

        // Registrar el pago
        $pago = PagosCuotum::create([
            'cuota_id'     => $cuota->id,
            'fecha_pago'   => $request->fecha_pago,
            'monto_pagado' => $request->monto_pagado,
            'comprobante'  => $rutaComprobante,
        ]);

        // Determinar si se completó la cuota
        if (round($nuevoTotal, 2) == round($cuota->monto, 2)) {
            $cuota->update([
                'situacion'  => 'pagado',
                'fecha_pago' => $request->fecha_pago,
            ]);
        } else {
            // Si aún falta pagar, mantener la situación (ej. "pendiente")
            $cuota->update([
                'situacion'  => 'pendiente',
            ]);
        }

        DB::commit();

        return response()->json([
            'status'  => 201,
            'message' => 'Pago registrado exitosamente.',
            'data'    => [
                'pago' => $pago->load('cuota'),
                'total_pagado' => $nuevoTotal,
                'restante' => round($cuota->monto - $nuevoTotal, 2),
                'situacion_cuota' => $cuota->situacion
            ]
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status'  => 500,
            'message' => 'Error al registrar el pago.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    /**
     * Método auxiliar para obtener la URL completa del comprobante
     */
    public function obtenerUrlComprobante($rutaComprobante)
    {
        if (!$rutaComprobante) {
            return null;
        }

        // Verificar si el archivo existe
        if (!Storage::disk('public')->exists($rutaComprobante)) {
            return null;
        }

        return Storage::url($rutaComprobante);
    }

    /**
     * Método para mostrar el comprobante directamente
     */
    public function mostrarComprobante($id)
    {
        $pago = PagosCuotum::find($id);

        if (!$pago || !$pago->comprobante) {
            return response()->json([
                'status' => 404,
                'message' => 'Comprobante no encontrado.'
            ], 404);
        }

        $rutaArchivo = storage_path('app/public/' . $pago->comprobante);

        if (!file_exists($rutaArchivo)) {
            return response()->json([
                'status' => 404,
                'message' => 'Archivo no encontrado en el servidor.'
            ], 404);
        }

        return response()->file($rutaArchivo);
    }
    public function show($id)
    {
        $pago = PagosCuotum::with('cuota')->find($id);

        if (!$pago) {
            return response()->json([
                'status' => 404,
                'message' => 'Pago no encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $pago
        ]);
    }

    public function update(Request $request, $id)
    {
        $pago = PagosCuotum::find($id);

        if (!$pago) {
            return response()->json([
                'status' => 404,
                'message' => 'Pago no encontrado.'
            ], 404);
        }

        $messages = [
            'cuota_id.exists' => 'La cuota seleccionada no existe.',
            'fecha_pago.date' => 'La fecha de pago debe ser válida.',
            'monto_pagado.numeric' => 'El monto pagado debe ser numérico.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido (imagen o PDF).',
        ];

        $validator = Validator::make($request->all(), [
            'cuota_id' => 'sometimes|exists:cuotas,id',
            'fecha_pago' => 'sometimes|date',
            'monto_pagado' => 'sometimes|numeric|min:0',
            'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->hasFile('comprobante')) {
                // Borrar el comprobante anterior si existe
                if ($pago->comprobante && Storage::disk('public')->exists($pago->comprobante)) {
                    Storage::disk('public')->delete($pago->comprobante);
                }

                // Guardar nuevo comprobante
                $pago->comprobante = $request->file('comprobante')->store('comprobantes', 'public');
            }

            $pago->update($request->only([
                'cuota_id',
                'fecha_pago',
                'monto_pagado'
            ]));

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Pago actualizado correctamente.',
                'data' => $pago->load('cuota')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $pago = PagosCuotum::find($id);

        if (!$pago) {
            return response()->json([
                'status' => 404,
                'message' => 'Pago no encontrado.'
            ], 404);
        }

        try {
            // Borrar el archivo comprobante si existe
            if ($pago->comprobante && Storage::disk('public')->exists($pago->comprobante)) {
                Storage::disk('public')->delete($pago->comprobante);
            }

            $pago->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Pago eliminado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
