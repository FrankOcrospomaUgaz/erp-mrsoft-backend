<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use App\Services\Facturacion\ComprobanteService;
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

        $query = Comprobante::with(['cliente.contactos_clientes', 'detalles'])
            ->latest()
            ->when($search, function ($query, $search) {
                $query->where('serie', 'ILIKE', "%{$search}%")
                    ->orWhereHas('cliente', function ($cliente) use ($search) {
                        $cliente->where('ruc', 'ILIKE', "%{$search}%")
                            ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%");
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

    public function show($id)
    {
        $comprobante = Comprobante::with(['cliente.contactos_clientes', 'detalles', 'facturador'])->find($id);

        if (!$comprobante) {
            return response()->json(['status' => 404, 'message' => 'Comprobante no encontrado'], 404);
        }

        return response()->json(['status' => 200, 'data' => new ComprobanteResource($comprobante)]);
    }

    public function store(Request $request)
    {
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

    public function emitir($id)
    {
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

    public function reenviarPendientes()
    {
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

    public function downloadXml($id)
    {
        return $this->downloadStoredFile((int) $id, 'xml_path', 'xml-request.json');
    }

    public function downloadCdr($id)
    {
        return $this->downloadStoredFile((int) $id, 'cdr_path', 'cdr-response.json');
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

    private function downloadStoredFile(int $id, string $field, string $filename)
    {
        $comprobante = Comprobante::find($id);

        if (!$comprobante || !$comprobante->{$field} || !Storage::disk('local')->exists($comprobante->{$field})) {
            return response()->json(['status' => 404, 'message' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('local')->download($comprobante->{$field}, $filename);
    }
}
