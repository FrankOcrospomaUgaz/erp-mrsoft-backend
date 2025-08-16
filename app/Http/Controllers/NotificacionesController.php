<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notificacione;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\NotificacioneResource;


class NotificacionesController extends Controller
{

public function index(Request $request)
{
    $search = $request->get('search');

    $notificaciones = Notificacione::with('cliente')
        ->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('detalle', 'ILIKE', "%{$search}%")
                  ->orWhereHas('cliente', function ($q2) use ($search) {
                      $q2->where('razon_social', 'ILIKE', "%{$search}%")
                         ->orWhere('ruc', 'ILIKE', "%{$search}%")
                         ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%")
                         ->orWhere('dueno_celular', 'ILIKE', "%{$search}%")
                         ->orWhere('dueno_email', 'ILIKE', "%{$search}%")
                         ->orWhere('representante_nombre', 'ILIKE', "%{$search}%")
                         ->orWhere('representante_celular', 'ILIKE', "%{$search}%")
                         ->orWhere('representante_email', 'ILIKE', "%{$search}%");
                  });
            });
        })
        ->latest()
        ->paginate($request->get('per_page', 5));

    return response()->json([
        'data' => NotificacioneResource::collection($notificaciones->items()),
        'links' => [
            'first' => $notificaciones->url(1),
            'last' => $notificaciones->url($notificaciones->lastPage()),
            'prev' => $notificaciones->previousPageUrl(),
            'next' => $notificaciones->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $notificaciones->currentPage(),
            'from' => $notificaciones->firstItem(),
            'last_page' => $notificaciones->lastPage(),
            'path' => $notificaciones->path(),
            'per_page' => $notificaciones->perPage(),
            'to' => $notificaciones->lastItem(),
            'total' => $notificaciones->total(),
        ]
    ]);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'detalle' => 'nullable|string'
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente no existe.',
            'detalle.string' => 'El detalle debe ser un texto.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $notificacion = Notificacione::create($request->only(['cliente_id', 'detalle']));

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Notificación creada exitosamente.',
                'data' => $notificacion
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar la notificación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $notificacion = Notificacione::with('cliente')->find($id);

        if (!$notificacion) {
            return response()->json([
                'status' => 404,
                'message' => 'Notificación no encontrada.'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $notificacion
        ]);
    }

    public function update(Request $request, $id)
    {
        $notificacion = Notificacione::find($id);

        if (!$notificacion) {
            return response()->json([
                'status' => 404,
                'message' => 'Notificación no encontrada.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'detalle' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $notificacion->update($request->only(['cliente_id', 'detalle']));

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Notificación actualizada correctamente.',
                'data' => $notificacion
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la notificación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $notificacion = Notificacione::find($id);

        if (!$notificacion) {
            return response()->json([
                'status' => 404,
                'message' => 'Notificación no encontrada.'
            ], 404);
        }

        try {
            $notificacion->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Notificación eliminada correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar la notificación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
