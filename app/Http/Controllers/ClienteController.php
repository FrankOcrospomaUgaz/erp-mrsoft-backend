<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Models\TiposUsuario;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $all = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $all ? null : $request->get('per_page', 10);

        $query = Cliente::query()
            ->whereNull('parent_cliente_id')
            ->with([
                'contactos_clientes',
                'contratos',
                'sucursales_clientes',
                'hijos_clientes.hijos_clientes',
                'avisos_saas',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ruc', 'ILIKE', "%{$search}%")
                        ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                        ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%")
                        ->orWhere('representante_nombre', 'ILIKE', "%{$search}%")
                        ->orWhere('responsable_nombre', 'ILIKE', "%{$search}%")
                        ->orWhereHas('hijos_clientes', function ($child) use ($search) {
                            $child->where('ruc', 'ILIKE', "%{$search}%")
                                ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                                ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                                ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%");
                        });
                });
            });

        if ($all) {
            $clientes = $query->get();

            return response()->json([
                'data' => ClienteResource::collection($clientes),
                'links' => null,
                'meta' => [
                    'total' => $clientes->count(),
                ],
            ]);
        }

        $clientes = $query->paginate($perPage);

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
            ],
        ]);
    }

    public function show($id)
    {
        $cliente = Cliente::with([
            'contactos_clientes',
            'contratos',
            'sucursales_clientes',
            'hijos_clientes.hijos_clientes',
            'avisos_saas',
        ])->find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => new ClienteResource($cliente),
        ], 200);
    }

    public function consultarRuc($ruc)
    {
        $validator = Validator::make(['ruc' => $ruc], [
            'ruc' => ['required', 'regex:/^\d{11}$/'],
        ], [
            'ruc.regex' => 'El RUC debe tener 11 digitos.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $configuredUrl = config('services.ruc_lookup.url');

        if (!$configuredUrl) {
            return response()->json([
                'status' => 503,
                'message' => 'La consulta de RUC no esta configurada en el servidor.',
            ], 503);
        }

        $url = str_contains($configuredUrl, '{ruc}')
            ? str_replace('{ruc}', $ruc, $configuredUrl)
            : rtrim($configuredUrl, '/') . '/' . $ruc;

        $request = Http::acceptJson()->timeout((int) config('services.ruc_lookup.timeout', 10));

        $token = config('services.ruc_lookup.token');
        if ($token) {
            $header = config('services.ruc_lookup.token_header', 'Authorization');
            $prefix = trim((string) config('services.ruc_lookup.token_prefix', 'Bearer'));
            $request = $request->withHeaders([
                $header => $prefix !== '' ? "{$prefix} {$token}" : $token,
            ]);
        }

        try {
            $response = $request->get($url);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 502,
                'message' => 'No se pudo consultar el servicio externo de RUC.',
                'error' => $e->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'status' => $response->status(),
                'message' => 'El servicio externo de RUC devolvio un error.',
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        $payload = $response->json();

        return response()->json([
            'status' => 200,
            'data' => $this->normalizeRucLookupResponse(is_array($payload) ? $payload : [], $ruc),
        ]);
    }

    public function consultarDni($dni)
    {
        $validator = Validator::make(['dni' => $dni], [
            'dni' => ['required', 'regex:/^\d{8}$/'],
        ], [
            'dni.regex' => 'El DNI debe tener 8 digitos.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $scheme = request()->isSecure() ? 'https' : 'http';
        $url = "{$scheme}://facturae-garzasoft.com/facturacion/buscaCliente/BuscaCliente2.php";

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($url, [
                    'dni' => $dni,
                    'fe' => 'N',
                    'token' => 'qusEj_w7aHEpX',
                ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 502,
                'message' => 'No se pudo consultar el servicio externo de DNI.',
                'error' => $e->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'status' => $response->status(),
                'message' => 'El servicio externo de DNI devolvio un error.',
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        $payload = $response->json();

        return response()->json([
            'status' => 200,
            'data' => $this->normalizeDniLookupResponse(is_array($payload) ? $payload : [], $dni),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->prepareClientePayload($request);
        $validator = $this->makeClienteValidator($data);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cliente = Cliente::create($this->extractClienteAttributes($data));
            $this->syncClienteRelations($cliente, $data);
            $this->ensureClientePortalUser($cliente->fresh());

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Cliente creado exitosamente',
                'data' => new ClienteResource($cliente->load([
                    'contactos_clientes',
                    'sucursales_clientes',
                    'hijos_clientes.hijos_clientes',
                ])),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $data = $this->prepareClientePayload($request);
        $validator = $this->makeClienteValidator($data, $cliente->id);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cliente->update($this->extractClienteAttributes($data));
            $this->syncClienteRelations($cliente, $data);
            $this->ensureClientePortalUser($cliente->fresh());

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Cliente actualizado correctamente',
                'data' => new ClienteResource($cliente->load([
                    'contactos_clientes',
                    'sucursales_clientes',
                    'hijos_clientes.hijos_clientes',
                ])),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $this->deleteChildrenRecursively($cliente);
        $cliente->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Cliente eliminado correctamente',
        ], 200);
    }

    public function sucursalesPorCliente($id)
    {
        $cliente = Cliente::with('hijos_clientes.hijos_clientes')->find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'hijos' => ClienteResource::collection($cliente->hijos_clientes),
        ]);
    }

    public function portalUser($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $usuario = Usuario::withTrashed()
            ->where('cliente_id', $cliente->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'cliente_id' => $cliente->id,
                'exists' => (bool) $usuario,
                'usuario' => $usuario ? [
                    'id' => $usuario->id,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'usuario' => $usuario->usuario,
                    'tipo_usuario_id' => $usuario->tipo_usuario_id,
                    'deleted_at' => $usuario->deleted_at,
                ] : null,
                'password_visible' => null,
                'password_message' => 'La contraseña actual no se puede ver porque esta guardada encriptada. Puedes definir una nueva.',
            ],
        ]);
    }

    public function savePortalUser(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $usuarioActual = Usuario::withTrashed()
            ->where('cliente_id', $cliente->id)
            ->latest()
            ->first();

        $validator = Validator::make($request->all(), [
            'usuario' => [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'usuario')->ignore($usuarioActual?->id),
            ],
            'password' => [
                $usuarioActual ? 'nullable' : 'required',
                'string',
                'min:4',
                'max:255',
            ],
            'nombres' => ['nullable', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'usuario.unique' => 'Ya existe otro usuario con ese login.',
            'password.required' => 'La contraseña es obligatoria para crear el acceso.',
            'password.min' => 'La contraseña debe tener al menos 4 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tipoCliente = TiposUsuario::firstOrCreate(['nombre' => 'Cliente']);
        $nombre = $request->input('nombres')
            ?: $cliente->razon_social
            ?: $cliente->nombre_comercial
            ?: $cliente->dueno_nombre
            ?: 'Cliente';

        $attributes = [
            'cliente_id' => $cliente->id,
            'nombres' => $nombre,
            'apellidos' => $request->input('apellidos') ?: null,
            'usuario' => $request->input('usuario'),
            'tipo_usuario_id' => $tipoCliente->id,
        ];

        if ($request->filled('password')) {
            $attributes['password'] = Hash::make($request->input('password'));
        }

        if ($usuarioActual) {
            if (method_exists($usuarioActual, 'trashed') && $usuarioActual->trashed()) {
                $usuarioActual->restore();
            }

            $usuarioActual->update($attributes);
            $usuario = $usuarioActual->fresh();
        } else {
            $usuario = Usuario::create($attributes);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Acceso de cliente guardado correctamente.',
            'data' => [
                'cliente_id' => $cliente->id,
                'exists' => true,
                'usuario' => [
                    'id' => $usuario->id,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'usuario' => $usuario->usuario,
                    'tipo_usuario_id' => $usuario->tipo_usuario_id,
                    'deleted_at' => $usuario->deleted_at,
                ],
                'password_visible' => $request->filled('password') ? $request->input('password') : null,
                'password_message' => $request->filled('password')
                    ? 'Nueva contraseña asignada. Solo se muestra en esta respuesta.'
                    : 'El usuario fue actualizado sin cambiar la contraseña.',
            ],
        ]);
    }

    private function makeClienteValidator(array $data, ?int $clienteId = null)
    {
        $tipo = $this->normalizeTipo($data['tipo'] ?? null);
        $isCorporacion = $tipo === 'corporacion';
        $isEmpresa = $tipo === 'empresa';
        $uniqueRuc = Rule::unique('clientes', 'ruc')->whereNull('deleted_at');

        if ($clienteId) {
            $uniqueRuc = $uniqueRuc->ignore($clienteId);
        }

        $validator = Validator::make($data, [
            'tipo' => 'required|in:corporacion,empresa,local,unico',
            'ruc' => ['nullable', 'string', 'max:20', $uniqueRuc],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'direccion' => [
                Rule::requiredIf($isEmpresa || $tipo === 'local'),
                'nullable',
                'string',
                'max:255',
            ],
            'tipos_local' => ['nullable', 'array'],
            'tipos_local.*' => ['string', Rule::exists('tipos_locales', 'codigo')->whereNull('deleted_at')],
            'contacto' => ['required', 'array'],
            'contacto.dni' => ['nullable', 'string', 'max:20'],
            'contacto.nombre' => ['required', 'string', 'max:255'],
            'contacto.celular' => ['nullable', 'string', 'max:20'],
            'contacto.email' => ['nullable', 'email', 'max:255'],
            'contacto.es_dueno' => ['nullable', 'boolean'],
            'contacto.es_vendedor' => ['nullable', 'boolean'],
            'contactos' => ['nullable', 'array'],
            'contactos.*.dni' => ['nullable', 'string', 'max:20'],
            'contactos.*.nombre' => ['required', 'string', 'max:255'],
            'contactos.*.celular' => ['nullable', 'string', 'max:20'],
            'contactos.*.email' => ['nullable', 'email', 'max:255'],
            'contactos.*.es_dueno' => ['nullable', 'boolean'],
            'contactos.*.es_vendedor' => ['nullable', 'boolean'],
            'hijos' => 'nullable|array',
        ], [
            'tipo.in' => 'El tipo debe ser corporacion, empresa o local.',
            'ruc.unique' => 'Ya existe un cliente con este RUC.',
            'nombre_comercial.required' => 'El nombre comercial es obligatorio.',
            'direccion.required' => 'La dirección es obligatoria para empresas y locales.',
            'tipos_local.*.exists' => 'Uno o más tipos de local no existen en el catálogo.',
            'contacto.required' => 'Debe registrar un contacto principal.',
            'contacto.nombre.required' => 'El nombre completo del contacto es obligatorio.',
        ]);

        $validator->after(function ($validator) use ($data, $tipo) {
            if ($tipo === 'local' && empty($data['tipos_local'])) {
                $validator->errors()->add('tipos_local', 'Debe seleccionar al menos un tipo para el local.');
            }
            $this->validateChildrenRecursively($data['hijos'] ?? [], $tipo, $validator, 'hijos');
        });

        return $validator;
    }

    private function prepareClientePayload(Request $request): array
    {
        $data = $request->all();
        $data['tipo'] = $this->normalizeTipo($data['tipo'] ?? null);
        $contactos = $this->normalizeContactosPayload($data['contactos'] ?? []);
        $contacto = $data['contacto'] ?? Arr::first($contactos) ?? [];
        $contacto = is_array($contacto) ? $contacto : [];
        $data['contacto'] = $this->normalizeContactoPayload($contacto);
        if (empty($data['contacto']['nombre']) && count($contactos) > 0) {
            $data['contacto'] = $contactos[0];
        }
        $data['contactos'] = count($contactos) > 0 ? $contactos : [$data['contacto']];
        $data['ruc'] = $this->emptyToNull($data['ruc'] ?? null);
        $data['razon_social'] = $this->emptyToNull($data['razon_social'] ?? null);
        $data['nombre_comercial'] = $this->emptyToNull($data['nombre_comercial'] ?? null);
        $data['direccion'] = $this->emptyToNull($data['direccion'] ?? null);
        $data['tipos_local'] = $this->normalizeTiposLocalPayload($data['tipos_local'] ?? []);
        $data['dueno_nombre'] = $data['contacto']['nombre'] ?? null;
        $data['dueno_celular'] = $data['contacto']['celular'] ?? null;
        $data['dueno_email'] = $data['contacto']['email'] ?? null;
        $data['dueno_es_representante'] = false;
        $data['dueno_es_responsable'] = false;
        $data['contacto_igual_empresa'] = (bool) ($data['contacto_igual_empresa'] ?? false);
        $data['representante_nombre'] = null;
        $data['representante_celular'] = null;
        $data['representante_email'] = null;
        $data['responsable_nombre'] = null;
        $data['responsable_celular'] = null;
        $data['responsable_email'] = null;
        $data['hijos'] = $this->normalizeChildrenPayload($data['hijos'] ?? $data['sucursales'] ?? []);

        return $data;
    }

    private function extractClienteAttributes(array $data): array
    {
        $contacto = $data['contacto'] ?? ['dni' => null, 'nombre' => null, 'celular' => null, 'email' => null];

        return Arr::only($data, [
            'parent_cliente_id',
            'tipo',
            'ruc',
            'razon_social',
            'nombre_comercial',
            'direccion',
            'tipos_local',
        ]) + [
            'dueno_nombre' => $contacto['nombre'] ?? null,
            'dueno_celular' => $contacto['celular'] ?? null,
            'dueno_email' => $contacto['email'] ?? null,
            'dueno_es_representante' => false,
            'dueno_es_responsable' => false,
            'contacto_igual_empresa' => (bool) ($data['contacto_igual_empresa'] ?? false),
            'representante_nombre' => null,
            'representante_celular' => null,
            'representante_email' => null,
            'responsable_nombre' => null,
            'responsable_celular' => null,
            'responsable_email' => null,
        ];
    }

    private function syncClienteRelations(Cliente $cliente, array $data): void
    {
        if (array_key_exists('contacto', $data) || array_key_exists('contactos', $data)) {
            $cliente->contactos_clientes()->delete();
            foreach ($data['contactos'] ?? [$data['contacto']] as $contacto) {
                $this->createContactoCliente($cliente, $contacto);
            }
        }

        $this->syncChildClientes($cliente, $data['hijos'] ?? []);
    }

    private function createChildCliente(Cliente $parent, array $data): Cliente
    {
        $data['parent_cliente_id'] = $parent->id;
        $child = Cliente::create($this->extractClienteAttributes($data));

        if (!empty($data['contacto']) || !empty($data['contactos'])) {
            foreach ($data['contactos'] ?? [$data['contacto']] as $contacto) {
                $this->createContactoCliente($child, $contacto);
            }
        }

        foreach ($data['hijos'] ?? [] as $hijo) {
            $this->createChildCliente($child, $hijo);
        }

        $this->ensureClientePortalUser($child->fresh());

        return $child;
    }

    private function syncChildClientes(Cliente $parent, array $children): void
    {
        $existingChildren = $parent->hijos_clientes()
            ->with(['contactos_clientes', 'hijos_clientes.hijos_clientes'])
            ->get()
            ->values();

        $usedChildIds = [];

        foreach ($children as $childData) {
            $matchedChild = $this->findMatchingChild($existingChildren, $childData, $usedChildIds);

            if ($matchedChild) {
                $usedChildIds[] = $matchedChild->id;
                $matchedChild->update($this->extractClienteAttributes([
                    ...$childData,
                    'parent_cliente_id' => $parent->id,
                ]));
                $this->syncClienteRelations($matchedChild, $childData);
                $this->ensureClientePortalUser($matchedChild->fresh());
                continue;
            }

            $this->createChildCliente($parent, $childData);
        }

        $existingChildren
            ->filter(fn(Cliente $child) => !in_array($child->id, $usedChildIds, true))
            ->each(function (Cliente $child) {
                $this->deleteChildrenRecursively($child);
                $child->delete();
            });
    }

    private function findMatchingChild($existingChildren, array $childData, array $usedChildIds): ?Cliente
    {
        $childType = $this->normalizeTipo($childData['tipo'] ?? null);
        $childRuc = $this->emptyToNull($childData['ruc'] ?? null);
        $childNombreComercial = $this->emptyToNull($childData['nombre_comercial'] ?? null);
        $childDireccion = $this->emptyToNull($childData['direccion'] ?? null);

        $availableChildren = $existingChildren->filter(function (Cliente $child) use ($usedChildIds, $childType) {
            return !in_array($child->id, $usedChildIds, true)
                && $this->normalizeTipo($child->tipo) === $childType;
        })->values();

        if ($availableChildren->isEmpty()) {
            return null;
        }

        if ($childRuc) {
            $matchedByRuc = $availableChildren->first(fn(Cliente $child) => $child->ruc === $childRuc);
            if ($matchedByRuc) {
                return $matchedByRuc;
            }
        }

        if ($childNombreComercial && $childDireccion) {
            $matchedByNameAndAddress = $availableChildren->first(function (Cliente $child) use ($childNombreComercial, $childDireccion) {
                return $this->emptyToNull($child->nombre_comercial) === $childNombreComercial
                    && $this->emptyToNull($child->direccion) === $childDireccion;
            });
            if ($matchedByNameAndAddress) {
                return $matchedByNameAndAddress;
            }
        }

        if ($childNombreComercial) {
            $matchedByName = $availableChildren->first(fn(Cliente $child) => $this->emptyToNull($child->nombre_comercial) === $childNombreComercial);
            if ($matchedByName) {
                return $matchedByName;
            }
        }

        return $availableChildren->first();
    }

    private function deleteChildrenRecursively(Cliente $cliente): void
    {
        $cliente->hijos_clientes()->with('hijos_clientes')->get()->each(function (Cliente $child) {
            $this->deleteChildrenRecursively($child);
            $child->delete();
        });
    }

    private function normalizeTipo(?string $tipo): ?string
    {
        return $tipo === 'unico' ? 'local' : $tipo;
    }

    private function normalizeContactoPayload(array $contacto): array
    {
        return [
            'dni' => $this->emptyToNull($contacto['dni'] ?? null),
            'nombre' => $this->emptyToNull($contacto['nombre'] ?? null),
            'celular' => $this->emptyToNull($contacto['celular'] ?? null),
            'email' => $this->emptyToNull($contacto['email'] ?? null),
            'es_dueno' => (bool) ($contacto['es_dueno'] ?? false),
            'es_vendedor' => (bool) ($contacto['es_vendedor'] ?? false),
        ];
    }

    private function normalizeContactosPayload($contactos): array
    {
        if (!is_array($contactos)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($contacto) {
            if (!is_array($contacto)) {
                return null;
            }

            $normalized = $this->normalizeContactoPayload($contacto);

            return !empty($normalized['nombre']) ? $normalized : null;
        }, $contactos)));
    }

    private function normalizeTiposLocalPayload($tipos): array
    {
        if (!is_array($tipos)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function ($tipo) {
            if (!is_string($tipo)) {
                return null;
            }

            $tipo = trim($tipo);

            return $tipo !== '' ? $tipo : null;
        }, $tipos))));
    }

    private function createContactoCliente(Cliente $cliente, array $contacto): void
    {
        if (empty($contacto['nombre'])) {
            return;
        }

        $cliente->contactos_clientes()->create([
            'dni' => $contacto['dni'] ?? null,
            'nombre' => $contacto['nombre'],
            'celular' => $contacto['celular'] ?? null,
            'email' => $contacto['email'] ?? null,
            'es_dueno' => (bool) ($contacto['es_dueno'] ?? false),
            'es_vendedor' => (bool) ($contacto['es_vendedor'] ?? false),
        ]);
    }

    private function ensureClientePortalUser(?Cliente $cliente): void
    {
        if (!$cliente || empty($cliente->ruc) || $this->normalizeTipo($cliente->tipo) === 'local') {
            return;
        }

        $tipoCliente = TiposUsuario::firstOrCreate(['nombre' => 'Cliente']);
        $nombre = $cliente->razon_social ?: $cliente->nombre_comercial ?: $cliente->dueno_nombre ?: 'Cliente';

        $usuario = Usuario::withTrashed()
            ->where(function ($query) use ($cliente) {
                $query->where('cliente_id', $cliente->id)
                    ->orWhere('usuario', $cliente->ruc);
            })
            ->first();

        if ($usuario) {
            if (method_exists($usuario, 'trashed') && $usuario->trashed()) {
                $usuario->restore();
            }

            $usuario->update([
                'cliente_id' => $cliente->id,
                'nombres' => $nombre,
                'apellidos' => '',
                'usuario' => $cliente->ruc,
                'tipo_usuario_id' => $tipoCliente->id,
            ]);

            return;
        }

        Usuario::create([
            'cliente_id' => $cliente->id,
            'nombres' => $nombre,
            'apellidos' => '',
            'usuario' => $cliente->ruc,
            'password' => Hash::make($cliente->ruc),
            'tipo_usuario_id' => $tipoCliente->id,
        ]);
    }

    private function emptyToNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeRucLookupResponse(array $payload, string $ruc): array
    {
        $data = Arr::get($payload, 'data', $payload);
        $raw = Arr::get($data, 'raw', []);
        $sources = [$data, is_array($raw) ? $raw : []];

        $read = function (array $keys) use ($sources) {
            foreach ($keys as $key) {
                foreach ($sources as $source) {
                    if (is_array($source) && array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                        return $source[$key];
                    }
                }
            }

            return null;
        };

        return [
            'ruc' => $read(['ruc', 'RUC', 'numeroDocumento']) ?? $ruc,
            'razon_social' => $read(['razon_social', 'RazonSocial', 'razonSocial', 'nombre_o_razon_social', 'nombre']),
            'nombre_comercial' => $read(['nombre_comercial', 'nombreComercial']),
            'direccion' => $read(['direccion', 'Direccion', 'direccion_completa', 'domicilioFiscal']),
            'estado' => $read(['estado', 'Estado']),
            'condicion' => $read(['condicion', 'Condicion']),
            'raw' => $payload,
        ];
    }

    private function normalizeDniLookupResponse(array $payload, string $dni): array
    {
        $data = Arr::get($payload, 'data', $payload);

        $nombres = Arr::get($data, 'nombres');
        $apepat = Arr::get($data, 'apepat');
        $apemat = Arr::get($data, 'apemat');

        $nombreCompleto = trim(implode(' ', array_filter([$nombres, $apepat, $apemat])));

        return [
            'dni' => Arr::get($data, 'dni', $dni),
            'nombres' => $nombres,
            'apepat' => $apepat,
            'apemat' => $apemat,
            'nombre_completo' => $nombreCompleto ?: null,
            'fechanac' => Arr::get($data, 'fecnac'),
            'raw' => $payload,
        ];
    }

    private function normalizeChildrenPayload(array $children): array
    {
        return array_map(function ($child) {
            $child['tipo'] = $this->normalizeTipo($child['tipo'] ?? null) ?? 'local';
            $child['ruc'] = $this->emptyToNull($child['ruc'] ?? null);
            $child['razon_social'] = $this->emptyToNull($child['razon_social'] ?? null);
            $child['nombre_comercial'] = $this->emptyToNull($child['nombre_comercial'] ?? null);
            $child['direccion'] = $this->emptyToNull($child['direccion'] ?? null);
            $child['tipos_local'] = $this->normalizeTiposLocalPayload($child['tipos_local'] ?? []);
            $childContactos = $this->normalizeContactosPayload($child['contactos'] ?? []);
            $child['contacto'] = $this->normalizeContactoPayload($child['contacto'] ?? Arr::first($childContactos) ?? []);
            if (empty($child['contacto']['nombre']) && count($childContactos) > 0) {
                $child['contacto'] = $childContactos[0];
            }
            $child['dueno_nombre'] = $child['contacto']['nombre'] ?? null;
            $child['dueno_celular'] = $child['contacto']['celular'] ?? null;
            $child['dueno_email'] = $child['contacto']['email'] ?? null;
            $child['dueno_es_representante'] = false;
            $child['dueno_es_responsable'] = false;
            $child['contacto_igual_empresa'] = (bool) ($child['contacto_igual_empresa'] ?? false);
            $child['representante_nombre'] = null;
            $child['representante_celular'] = null;
            $child['representante_email'] = null;
            $child['responsable_nombre'] = null;
            $child['responsable_celular'] = null;
            $child['responsable_email'] = null;
            $child['contactos'] = count($childContactos) > 0 ? $childContactos : [$child['contacto']];
            $child['hijos'] = $this->normalizeChildrenPayload($child['hijos'] ?? []);

            return $child;
        }, array_values($children));
    }

    private function validateChildrenRecursively(array $children, ?string $parentType, $validator, string $pathPrefix): void
    {
        $parentType = $this->normalizeTipo($parentType);

        foreach ($children as $index => $child) {
            $childPath = "{$pathPrefix}.{$index}";
            $childType = $this->normalizeTipo($child['tipo'] ?? null);
            $allowedTypes = match ($parentType) {
                'corporacion' => ['empresa'],
                'empresa' => ['local'],
                default => [],
            };

            if (!$childType) {
                $validator->errors()->add("{$childPath}.tipo", 'El tipo es obligatorio.');
                continue;
            }

            if (!in_array($childType, $allowedTypes, true)) {
                $validator->errors()->add(
                    "{$childPath}.tipo",
                    $parentType === 'corporacion'
                        ? 'Una corporación solo puede contener empresas.'
                        : 'Una empresa solo puede contener locales.'
                );
            }

            if (empty($child['nombre_comercial'])) {
                $validator->errors()->add("{$childPath}.nombre_comercial", 'El nombre comercial es obligatorio.');
            }

            if (in_array($childType, ['empresa', 'local'], true) && empty($child['direccion'])) {
                $validator->errors()->add("{$childPath}.direccion", 'La dirección es obligatoria.');
            }

            if ($childType === 'local' && empty($child['tipos_local'])) {
                $validator->errors()->add("{$childPath}.tipos_local", 'Debe seleccionar al menos un tipo para el local.');
            }

            if (empty($child['contacto']['nombre'])) {
                $validator->errors()->add("{$childPath}.contacto.nombre", 'El nombre completo del contacto es obligatorio.');
            }

            $this->validateChildrenRecursively($child['hijos'] ?? [], $childType, $validator, "{$childPath}.hijos");
        }
    }
}
