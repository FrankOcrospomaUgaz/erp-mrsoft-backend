<?php

namespace App\Http\Controllers;

use App\Models\ContactosCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ContactoController extends Controller
{
    /**
     * GET /contactos
     * List all contacts with pagination/search.
     */
    public function index(Request $request)
    {
        $search  = $request->get('search');
        $all     = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $all ? null : (int) $request->get('per_page', 15);

        $query = ContactosCliente::query()
            ->with('cliente:id,nombre_comercial,tipo')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nombre',   'ILIKE', "%{$search}%")
                          ->orWhere('dni',    'ILIKE', "%{$search}%")
                          ->orWhere('celular', 'ILIKE', "%{$search}%")
                          ->orWhere('email',  'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('nombre');

        if ($all) {
            $items = $query->get();
            return response()->json([
                'data' => $items->map(fn($c) => $this->format($c)),
                'meta' => ['total' => $items->count()],
            ]);
        }

        $paged = $query->paginate($perPage);

        return response()->json([
            'data'  => collect($paged->items())->map(fn($c) => $this->format($c)),
            'links' => [
                'first' => $paged->url(1),
                'last'  => $paged->url($paged->lastPage()),
                'prev'  => $paged->previousPageUrl(),
                'next'  => $paged->nextPageUrl(),
            ],
            'meta'  => [
                'current_page' => $paged->currentPage(),
                'from'         => $paged->firstItem(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'to'           => $paged->lastItem(),
                'total'        => $paged->total(),
            ],
        ]);
    }

    /**
     * GET /contactos/{id}
     */
    public function show($id)
    {
        $contacto = ContactosCliente::with('cliente:id,nombre_comercial,tipo')->find($id);

        if (!$contacto) {
            return response()->json(['status' => 404, 'message' => 'Contacto no encontrado'], 404);
        }

        return response()->json(['status' => 200, 'data' => $this->format($contacto)]);
    }

    /**
     * GET /contactos/buscar-dni/{dni}
     * 1. Search local DB first.
     * 2. If not found, call web service.
     */
    public function buscarPorDni($dni)
    {
        $validator = Validator::make(['dni' => $dni], [
            'dni' => ['required', 'regex:/^\d{8}$/'],
        ], ['dni.regex' => 'El DNI debe tener 8 digitos.']);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        // 1. Local DB first (most recently updated record with this DNI)
        $localContact = ContactosCliente::where('dni', $dni)
            ->whereNotNull('nombre')
            ->orderByDesc('updated_at')
            ->first();

        if ($localContact) {
            return response()->json([
                'status' => 200,
                'source' => 'local',
                'data'   => [
                    'id'          => $localContact->id,
                    'dni'         => $localContact->dni,
                    'nombre'      => $localContact->nombre,
                    'celular'     => $localContact->celular,
                    'email'       => $localContact->email,
                    'es_dueno'    => (bool) $localContact->es_dueno,
                    'es_vendedor' => (bool) $localContact->es_vendedor,
                ],
            ]);
        }

        // 2. Web service fallback
        $scheme = request()->isSecure() ? 'https' : 'http';
        $url = "{$scheme}://facturae-garzasoft.com/facturacion/buscaCliente/BuscaCliente2.php";

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($url, [
                    'dni'   => $dni,
                    'fe'    => 'N',
                    'token' => 'qusEj_w7aHEpX',
                ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 502,
                'source'  => 'error',
                'message' => 'No se pudo consultar el servicio externo de DNI.',
                'error'   => $e->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'status'  => $response->status(),
                'source'  => 'error',
                'message' => 'El servicio externo de DNI devolvio un error.',
            ], $response->status());
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            return response()->json([
                'status'  => 404,
                'source'  => 'not_found',
                'message' => 'No se encontro informacion para este DNI.',
            ], 404);
        }

        $nombres        = $payload['nombres']  ?? $payload['nombre'] ?? null;
        $apepat         = $payload['apepat']   ?? null;
        $apemat         = $payload['apemat']   ?? null;
        $nombreCompleto = trim(implode(' ', array_filter([$nombres, $apepat, $apemat])));

        if (!$nombreCompleto) {
            return response()->json([
                'status'  => 404,
                'source'  => 'not_found',
                'message' => 'No se encontro informacion para este DNI.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'source' => 'web_service',
            'data'   => [
                'id'          => null,
                'dni'         => $dni,
                'nombre'      => $nombreCompleto,
                'celular'     => null,
                'email'       => null,
                'es_dueno'    => false,
                'es_vendedor' => false,
            ],
        ]);
    }

    /**
     * PUT /contactos/{id}
     * Update contact. Also propagates celular/email/nombre to all contacts with same DNI.
     */
    public function update(Request $request, $id)
    {
        $contacto = ContactosCliente::find($id);

        if (!$contacto) {
            return response()->json(['status' => 404, 'message' => 'Contacto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'celular'     => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:255'],
            'es_dueno'    => ['nullable', 'boolean'],
            'es_vendedor' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $fields = array_filter(
            $request->only(['nombre', 'celular', 'email', 'es_dueno', 'es_vendedor']),
            fn($v) => $v !== null
        );

        $contacto->update($fields);

        // Propagate phone/email changes to all other contacts sharing the same DNI
        if ($contacto->dni && !empty($fields)) {
            ContactosCliente::where('dni', $contacto->dni)
                ->where('id', '!=', $contacto->id)
                ->update($fields);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Contacto actualizado correctamente.',
            'data'    => $this->format($contacto->fresh()->load('cliente:id,nombre_comercial,tipo')),
        ]);
    }

    /**
     * DELETE /contactos/{id}
     */
    public function destroy($id)
    {
        $contacto = ContactosCliente::find($id);

        if (!$contacto) {
            return response()->json(['status' => 404, 'message' => 'Contacto no encontrado'], 404);
        }

        $contacto->delete();

        return response()->json(['status' => 200, 'message' => 'Contacto eliminado correctamente.']);
    }

    private function format(ContactosCliente $c): array
    {
        return [
            'id'             => $c->id,
            'cliente_id'     => $c->cliente_id,
            'cliente_nombre' => $c->cliente?->nombre_comercial,
            'cliente_tipo'   => $c->cliente?->tipo,
            'dni'            => $c->dni,
            'nombre'         => $c->nombre,
            'celular'        => $c->celular,
            'email'          => $c->email,
            'es_dueno'       => (bool) $c->es_dueno,
            'es_vendedor'    => (bool) $c->es_vendedor,
            'created_at'     => $c->created_at?->toISOString(),
            'updated_at'     => $c->updated_at?->toISOString(),
        ];
    }
}
