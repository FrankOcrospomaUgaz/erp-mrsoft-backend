<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ContratoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContratoDocumentoController extends Controller
{
    public function index(Request $request, $contrato)
    {
        $contract = Contrato::findOrFail($contrato);
        $this->ensureAccess($request, $contract);

        return response()->json([
            'status' => 200,
            'data' => $contract->documentos()->latest()->get()->map(fn (ContratoDocumento $documento) => $this->format($documento)),
        ]);
    }

    public function store(Request $request, $contrato)
    {
        $contract = Contrato::findOrFail($contrato);
        $files = $request->file('documentos', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $request->validate([
            'documentos' => ['required', 'array', 'min:1', 'max:20'],
            'documentos.*' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,webp,zip',
            ],
        ], [
            'documentos.required' => 'Selecciona al menos un documento.',
            'documentos.max' => 'Puedes subir hasta 20 documentos por vez.',
            'documentos.*.max' => 'Cada archivo puede pesar como máximo 50 MB.',
            'documentos.*.mimes' => 'El formato de uno de los archivos no está permitido.',
        ]);

        $documents = [];
        foreach ($files as $file) {
            $path = $file->store("contratos/{$contract->id}/documentos", 'public');
            $documents[] = $contract->documentos()->create([
                'nombre_original' => $file->getClientOriginalName(),
                'ruta' => $path,
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'tamanio' => $file->getSize(),
            ]);
        }

        return response()->json([
            'status' => 201,
            'message' => 'Documentos subidos correctamente.',
            'data' => collect($documents)->map(fn (ContratoDocumento $documento) => $this->format($documento)),
        ], 201);
    }

    public function download(Request $request, $documento)
    {
        $document = ContratoDocumento::with('contrato')->findOrFail($documento);
        $this->ensureAccess($request, $document->contrato);

        abort_unless(Storage::disk('public')->exists($document->ruta), 404, 'El documento ya no está disponible.');

        return Storage::disk('public')->download(
            $document->ruta,
            $document->nombre_original,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream']
        );
    }

    public function destroy(Request $request, $documento)
    {
        $document = ContratoDocumento::findOrFail($documento);
        Storage::disk('public')->delete($document->ruta);
        $document->delete();

        return response()->json(['status' => 200, 'message' => 'Documento eliminado correctamente.']);
    }

    private function format(ContratoDocumento $documento): array
    {
        return [
            'id' => $documento->id,
            'contrato_id' => $documento->contrato_id,
            'nombre_original' => $documento->nombre_original,
            'mime_type' => $documento->mime_type,
            'tamanio' => $documento->tamanio,
            'created_at' => $documento->created_at?->toIso8601String(),
        ];
    }

    private function ensureAccess(Request $request, Contrato $contract): void
    {
        $user = $request->user();
        if (!$user?->cliente_id) {
            return;
        }

        $allowed = [(int) $user->cliente_id];
        $pending = $allowed;
        while ($pending) {
            $children = \App\Models\Cliente::whereIn('parent_cliente_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $children = array_values(array_diff($children, $allowed));
            $allowed = array_values(array_unique([...$allowed, ...$children]));
            $pending = $children;
        }

        abort_unless(in_array((int) $contract->cliente_id, $allowed, true), 403, 'No autorizado.');
    }
}
