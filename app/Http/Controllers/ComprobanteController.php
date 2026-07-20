<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComprobanteResource;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Facturador;
use App\Services\Facturacion\ComprobanteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComprobanteController extends Controller
{
    public function __construct(private readonly ComprobanteService $service)
    {
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 10);
        $clienteIds = $this->accessibleClienteIds($request);

        $query = Comprobante::with(['cliente.contactos_clientes', 'detalles'])
            ->latest()
            ->when($clienteIds, fn ($query) => $query->whereIn('cliente_id', $clienteIds))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('serie', 'ILIKE', "%{$search}%")
                    ->orWhereRaw('CAST(correlativo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('cliente', function ($cliente) use ($search) {
                        $cliente->where('ruc', 'ILIKE', "%{$search}%")
                            ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%");
                    });
                });
            });

        $comprobantes = $query->paginate($perPage);

        return response()->json([
            'data' => ComprobanteResource::collection($comprobantes->items()),
            'links' => [
                'first' => $comprobantes->url(1),
                'last' => $comprobantes->url($comprobantes->lastPage()),
                'prev' => $comprobantes->previousPageUrl(),
                'next' => $comprobantes->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $comprobantes->currentPage(),
                'last_page' => $comprobantes->lastPage(),
                'per_page' => $comprobantes->perPage(),
                'total' => $comprobantes->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $comprobante = Comprobante::with(['cliente.contactos_clientes', 'detalles', 'facturador'])->find($id);

        if (!$comprobante) {
            return response()->json(['status' => 404, 'message' => 'Comprobante no encontrado'], 404);
        }

        if (!$this->canAccessComprobante($request, $comprobante)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        return response()->json(['status' => 200, 'data' => new ComprobanteResource($comprobante)]);
    }

    public function store(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $comprobante = $this->service->crear($validator->validated(), $request->boolean('emitir'));

        return response()->json([
            'status' => 201,
            'message' => 'Comprobante creado correctamente',
            'data' => new ComprobanteResource($comprobante),
        ], 201);
    }

    public function emitir(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $comprobante = Comprobante::find($id);

        if (!$comprobante) {
            return response()->json(['status' => 404, 'message' => 'Comprobante no encontrado'], 404);
        }

        $emitido = $this->service->emitir($comprobante);

        return response()->json([
            'status' => 200,
            'message' => $emitido->estado === 'X' ? 'Comprobante con error de emision' : 'Comprobante emitido correctamente',
            'data' => new ComprobanteResource($emitido),
        ]);
    }

    public function emisionMasiva(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $validator = $this->validator($request->all(), true);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $resultados = $this->service->emitirMasivo($validator->validated() + [
            'emitir' => $request->boolean('emitir', true),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Proceso masivo terminado',
            'data' => collect($resultados)->map(function ($item) {
                if (($item['ok'] ?? false) && isset($item['comprobante'])) {
                    $item['comprobante'] = new ComprobanteResource($item['comprobante']);
                }

                return $item;
            })->values(),
        ]);
    }

    public function reenviarPendientes(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $pendientes = Comprobante::whereIn('estado', ['E', 'X'])->get();
        $resultados = [];

        foreach ($pendientes as $comprobante) {
            try {
                $resultados[] = [
                    'id' => $comprobante->id,
                    'ok' => true,
                    'comprobante' => new ComprobanteResource($this->service->emitir($comprobante)),
                ];
            } catch (\Throwable $e) {
                $resultados[] = [
                    'id' => $comprobante->id,
                    'ok' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['status' => 200, 'data' => $resultados]);
    }

    public function downloadXml(Request $request, $id)
    {
        return $this->downloadStoredFile($request, (int) $id, 'xml_path', 'xml-request.json');
    }

    public function downloadCdr(Request $request, $id)
    {
        return $this->downloadStoredFile($request, (int) $id, 'cdr_path', 'cdr-response.json');
    }

    public function pdf(Request $request, $id)
    {
        $comprobante = Comprobante::with([
            'cliente.contactos_clientes',
            'detalles.producto',
            'detalles.modulo',
            'facturador',
        ])->find($id);

        if (!$comprobante) {
            return response()->json(['status' => 404, 'message' => 'Comprobante no encontrado'], 404);
        }

        if (!$this->canAccessComprobante($request, $comprobante)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $facturador = $comprobante->facturador
            ?? Facturador::where('activo', true)->latest()->first()
            ?? Facturador::latest()->first();

        $cliente = $comprobante->cliente;
        $contacto = $cliente?->contactos_clientes?->first();
        $tipoDocumento = match ($comprobante->tipo_documento) {
            'F' => 'FACTURA DE VENTA ELECTRONICA',
            'B' => 'BOLETA DE VENTA ELECTRONICA',
            'C' => 'NOTA DE CREDITO ELECTRONICA',
            'D' => 'NOTA DE DEBITO ELECTRONICA',
            default => 'COMPROBANTE ELECTRONICO',
        };

        $pdf = Pdf::loadView('pdf.comprobante', [
            'comprobante' => $comprobante,
            'facturador' => $facturador,
            'cliente' => $cliente,
            'contacto' => $contacto,
            'tipoDocumento' => $tipoDocumento,
            'numeroComprobante' => $comprobante->serie . '-' . str_pad((string) $comprobante->correlativo, 6, '0', STR_PAD_LEFT),
            'monedaSimbolo' => strtoupper((string) $comprobante->moneda) === 'USD' ? '$' : 'S/',
            'formaPago' => $comprobante->forma_pago === 'D' ? 'CREDITO' : 'CONTADO',
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprobante-' . $comprobante->serie . '-' . $comprobante->correlativo . '.pdf"',
        ]);
    }

    private function validator(array $data, bool $masivo = false)
    {
        return Validator::make($data, [
            'cliente_id' => [$masivo ? 'nullable' : 'required', 'integer', 'exists:clientes,id'],
            'cliente_ids' => [$masivo ? 'required' : 'nullable', 'array'],
            'cliente_ids.*' => ['integer', 'exists:clientes,id'],
            'contrato_id' => ['nullable', 'integer', 'exists:contratos,id'],
            'cuota_id' => ['nullable', 'integer', 'exists:cuotas,id'],
            'facturador_id' => ['nullable', 'integer', 'exists:facturadores,id'],
            'tipo_documento' => ['required', Rule::in(['F', 'B'])],
            'serie' => ['nullable', 'string', 'max:8'],
            'correlativo' => ['nullable', 'integer', 'min:1'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'forma_pago' => ['nullable', Rule::in(['C', 'D'])],
            'fecha_emision' => ['nullable', 'date'],
            'emitir' => ['nullable', 'boolean'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'detalles.*.modulo_id' => ['nullable', 'integer', 'exists:modulos,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'detalles.*.tipo_igv' => ['nullable', 'string', 'max:4'],
            'detalles.*.unidad' => ['nullable', 'string', 'max:8'],
        ], [
            'cliente_ids.required' => 'Debe seleccionar al menos un cliente.',
            'detalles.required' => 'Debe registrar al menos un detalle.',
            'detalles.*.descripcion.required' => 'La descripcion del detalle es obligatoria.',
        ]);
    }

    private function downloadStoredFile(Request $request, int $id, string $field, string $filename)
    {
        $comprobante = Comprobante::find($id);

        if ($comprobante && !$this->canAccessComprobante($request, $comprobante)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        if (!$comprobante || !$comprobante->{$field} || !Storage::disk('local')->exists($comprobante->{$field})) {
            return response()->json(['status' => 404, 'message' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('local')->download($comprobante->{$field}, $filename);
    }

    private function canAccessComprobante(Request $request, Comprobante $comprobante): bool
    {
        $clienteIds = $this->accessibleClienteIds($request);

        return !$clienteIds || in_array((int) $comprobante->cliente_id, $clienteIds, true);
    }

    private function accessibleClienteIds(Request $request): array
    {
        $clienteId = $request->user()?->cliente_id;

        if (!$clienteId) {
            return [];
        }

        $ids = [(int) $clienteId];
        $pending = [(int) $clienteId];

        while (!empty($pending)) {
            $children = Cliente::query()
                ->whereIn('parent_cliente_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $children)));
            $pending = $children;
        }

        return $ids;
    }
}
