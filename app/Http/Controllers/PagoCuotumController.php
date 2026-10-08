<?php

namespace App\Http\Controllers;

use App\Models\PagosCuotum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\PagosCuotumResource;
use App\Models\Cuota;
use App\Models\Configuracion;
use App\Services\ExternalEmailService;
use Illuminate\Support\Facades\Log;

class PagoCuotumController extends Controller
{
    public function __construct(private ExternalEmailService $emailService) {}

    public function index(Request $request)
    {
        $query = PagosCuotum::with(['cuota.contrato.cliente']);

        if ($request->filled('estado_revision')) {
            $query->where('estado_revision', $request->get('estado_revision'));
        }

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

    public function storeManual(Request $request, Cuota $cuota)
    {
        $clienteIds = $this->accessibleClienteIds($request);
        $cuota->load('contrato');
        if (!$clienteIds || !in_array((int) $cuota->contrato?->cliente_id, $clienteIds, true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'fecha_pago' => ['required', 'date'],
            'monto_pagado' => ['required', 'numeric', 'min:0.01'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($cuota->situacion === 'pagado') {
            return response()->json(['message' => 'Esta cuota ya fue pagada.'], 422);
        }

        $aprobado = PagosCuotum::where('cuota_id', $cuota->id)->where('estado_revision', 'aprobado')->sum('monto_pagado');
        $pendiente = PagosCuotum::where('cuota_id', $cuota->id)->where('estado_revision', 'pendiente')->exists();
        if ($pendiente) {
            return response()->json(['message' => 'Ya existe un comprobante pendiente de revisión para esta cuota.'], 422);
        }
        if ($aprobado + (float) $validated['monto_pagado'] > (float) $cuota->monto) {
            return response()->json(['message' => 'El importe supera el saldo pendiente de la cuota.'], 422);
        }

        $pago = PagosCuotum::create([
            'cuota_id' => $cuota->id,
            'fecha_pago' => $validated['fecha_pago'],
            'monto_pagado' => $validated['monto_pagado'],
            'comprobante' => $request->file('comprobante')->store('comprobantes', 'public'),
            'metodo_pago' => 'manual',
            'estado_revision' => 'pendiente',
        ]);

        $notificationEmail = Configuracion::where('clave', 'manual_payment_notification_email')->value('valor') ?: env('PAYMENT_REVIEW_EMAIL');
        if (!$notificationEmail) {
            return response()->json(['message' => 'El comprobante fue guardado, pero no hay un correo de revisión configurado. Configúralo para enviar el aviso.'], 503);
        }
        try {
            $this->emailService->notifyManualPaymentSubmitted($pago, $this->reviewUrl($pago), $notificationEmail);
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar aviso de nuevo comprobante.', ['pago_id' => $pago->id, 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'El comprobante fue guardado, pero no se pudo enviar el correo de aviso. Puedes usar “Reenviar comprobante”.', 'error' => $exception->getMessage()], 502);
        }

        return response()->json(['message' => 'Comprobante enviado. Quedará pendiente de validación.', 'data' => new PagosCuotumResource($pago)], 201);
    }

    public function approve(Request $request, PagosCuotum $pago)
    {
        if ($pago->estado_revision !== 'pendiente') {
            return response()->json(['message' => 'Este comprobante ya fue revisado.'], 422);
        }

        $validated = $request->validate(['observacion_revision' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $pago, $validated) {
            $cuota = Cuota::lockForUpdate()->findOrFail($pago->cuota_id);
            $approvedTotal = PagosCuotum::where('cuota_id', $cuota->id)->where('estado_revision', 'aprobado')->sum('monto_pagado');
            if ($approvedTotal + (float) $pago->monto_pagado > (float) $cuota->monto) {
                abort(422, 'La aprobación supera el importe pendiente de la cuota.');
            }
            $pago->update(['estado_revision' => 'aprobado', 'observacion_revision' => $validated['observacion_revision'] ?? null, 'revisado_por' => $request->user()->id, 'revisado_at' => now()]);
            $total = $approvedTotal + (float) $pago->monto_pagado;
            $cuota->update([
                'situacion' => round($total, 2) >= round((float) $cuota->monto, 2) ? 'pagado' : $cuota->situacion,
                'fecha_pago' => round($total, 2) >= round((float) $cuota->monto, 2) ? $pago->fecha_pago : $cuota->fecha_pago,
            ]);
        });

        $this->notifyClientDecision($pago, true, $validated['observacion_revision'] ?? null);

        return response()->json(['message' => 'Comprobante aprobado y cuota actualizada.']);
    }

    public function reject(Request $request, PagosCuotum $pago)
    {
        if ($pago->estado_revision !== 'pendiente') {
            return response()->json(['message' => 'Este comprobante ya fue revisado.'], 422);
        }
        $validated = $request->validate(['observacion_revision' => ['nullable', 'string', 'max:1000']]);
        $pago->update(['estado_revision' => 'rechazado', 'motivo_rechazo' => $validated['observacion_revision'] ?? null, 'observacion_revision' => $validated['observacion_revision'] ?? null, 'revisado_por' => $request->user()->id, 'revisado_at' => now()]);
        $this->notifyClientDecision($pago, false, $validated['observacion_revision'] ?? null);
        return response()->json(['message' => 'Comprobante rechazado.']);
    }

    public function resendManualNotification(Request $request, Cuota $cuota)
    {
        $clienteIds = $this->accessibleClienteIds($request);
        $cuota->load(['contrato.cliente', 'pagos_cuota']);
        if (!$clienteIds || !in_array((int) $cuota->contrato?->cliente_id, $clienteIds, true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $pago = $cuota->pagos_cuota
            ->where('metodo_pago', 'manual')
            ->where('estado_revision', 'pendiente')
            ->sortByDesc('id')
            ->first();
        if (!$pago) {
            return response()->json(['message' => 'No existe un comprobante pendiente para reenviar.'], 422);
        }

        $notificationEmail = Configuracion::where('clave', 'manual_payment_notification_email')->value('valor') ?: env('PAYMENT_REVIEW_EMAIL');
        if (!$notificationEmail) {
            return response()->json(['message' => 'No hay un correo de revisión configurado.'], 503);
        }
        try {
            $this->emailService->notifyManualPaymentSubmitted($pago, $this->reviewUrl($pago), $notificationEmail);
            return response()->json(['message' => 'El aviso fue reenviado correctamente.']);
        } catch (\Throwable $exception) {
            Log::error('No se pudo reenviar aviso de comprobante.', ['pago_id' => $pago->id, 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'No se pudo reenviar el correo de aviso.', 'error' => $exception->getMessage()], 502);
        }
    }

    private function reviewUrl(PagosCuotum $pago): string
    {
        return rtrim(env('CLIENT_APP_URL', config('app.url')), '/') . '/pagos-por-aprobar?pago=' . $pago->id;
    }

    private function notifyClientDecision(PagosCuotum $pago, bool $approved, ?string $comment): void
    {
        $pago->loadMissing('cuota.contrato.cliente');
        $cliente = $pago->cuota?->contrato?->cliente;
        $email = $cliente?->dueno_email ?: $cliente?->representante_email;
        if (!$email) return;
        try {
            $this->emailService->notifyManualPaymentDecision($pago, $approved, $comment, $email);
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar decisión de comprobante al cliente.', ['pago_id' => $pago->id, 'error' => $exception->getMessage()]);
        }
    }

    private function accessibleClienteIds(Request $request): array
    {
        $clienteId = $request->user()?->cliente_id;
        return $clienteId ? [$clienteId] : [];
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
        $totalPagosPrevios = PagosCuotum::where('cuota_id', $cuota->id)->where('estado_revision', 'aprobado')->sum('monto_pagado');
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
            'metodo_pago' => 'manual',
            'estado_revision' => 'aprobado',
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
    public function mostrarComprobante(PagosCuotum $pago)
    {
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
