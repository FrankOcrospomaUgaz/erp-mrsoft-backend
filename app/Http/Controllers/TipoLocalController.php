<?php

namespace App\Http\Controllers;

use App\Http\Resources\TipoLocalResource;
use App\Models\TipoLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TipoLocalController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $all = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $request->get('per_page', 10);

        $query = TipoLocal::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('nombre', 'ILIKE', "%{$search}%")
                        ->orWhere('codigo', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('nombre');

        if ($all) {
            $tiposLocal = $query->get();

            return response()->json([
                'data' => TipoLocalResource::collection($tiposLocal),
                'links' => null,
                'meta' => [
                    'total' => $tiposLocal->count(),
                ],
            ]);
        }

        $tiposLocal = $query->paginate($perPage);

        return response()->json([
            'data' => TipoLocalResource::collection($tiposLocal->items()),
            'links' => [
                'first' => $tiposLocal->url(1),
                'last' => $tiposLocal->url($tiposLocal->lastPage()),
                'prev' => $tiposLocal->previousPageUrl(),
                'next' => $tiposLocal->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $tiposLocal->currentPage(),
                'from' => $tiposLocal->firstItem(),
                'last_page' => $tiposLocal->lastPage(),
                'path' => $tiposLocal->path(),
                'per_page' => $tiposLocal->perPage(),
                'to' => $tiposLocal->lastItem(),
                'total' => $tiposLocal->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:tipos_locales,nombre',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de local con ese nombre.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tipoLocal = TipoLocal::create([
                'nombre' => $request->nombre,
                'codigo' => $this->buildUniqueCode($request->nombre),
            ]);

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Tipo de local creado exitosamente',
                'data' => new TipoLocalResource($tipoLocal),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el tipo de local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $tipoLocal = TipoLocal::find($id);

        if (!$tipoLocal) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de local no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Detalle del tipo de local',
            'data' => new TipoLocalResource($tipoLocal),
        ]);
    }

    public function update(Request $request, $id)
    {
        $tipoLocal = TipoLocal::find($id);

        if (!$tipoLocal) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de local no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => "required|string|max:255|unique:tipos_locales,nombre,{$id}",
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de local con ese nombre.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tipoLocal->update([
                'nombre' => $request->nombre,
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Tipo de local actualizado exitosamente',
                'data' => new TipoLocalResource($tipoLocal->fresh()),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el tipo de local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $tipoLocal = TipoLocal::find($id);

        if (!$tipoLocal) {
            return response()->json([
                'status' => 404,
                'message' => 'Tipo de local no encontrado',
            ], 404);
        }

        $enUso = DB::table('clientes')
            ->whereNull('deleted_at')
            ->whereJsonContains('tipos_local', $tipoLocal->codigo)
            ->exists();

        if ($enUso) {
            return response()->json([
                'status' => 422,
                'message' => 'No se puede eliminar este tipo de local porque ya está asignado a uno o más clientes.',
            ], 422);
        }

        try {
            $tipoLocal->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Tipo de local eliminado correctamente',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el tipo de local',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildUniqueCode(string $nombre): string
    {
        $baseCode = Str::of($nombre)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        $baseCode = $baseCode !== '' ? $baseCode : 'tipo_local';
        $code = $baseCode;
        $suffix = 1;

        while (TipoLocal::withTrashed()->where('codigo', $code)->exists()) {
            $code = "{$baseCode}_{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
