<?php

namespace App\Http\Controllers;

use App\Exports\ProfessionalReportExport;
use App\Models\Modulo;
use App\Models\Producto;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function options()
    {
        return response()->json(['status' => 200, 'data' => $this->reports->options()]);
    }

    public function show(Request $request, string $type)
    {
        $report = $this->reports->make($type, $request);
        return response()->json(['status' => 200, 'data' => $report]);
    }

    public function excel(Request $request, string $type)
    {
        $report = $this->reports->make($type, $request);
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His').'.xlsx';
        return Excel::download(new ProfessionalReportExport($report, $this->friendlyFilters($request)), $filename);
    }

    public function pdf(Request $request, string $type)
    {
        $report = $this->reports->make($type, $request);
        $pdf = Pdf::loadView('pdf.report', [
            'report' => $report,
            'filters' => $this->friendlyFilters($request),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream(Str::slug($report['title']).'-'.now()->format('Ymd-His').'.pdf');
    }

    private function friendlyFilters(Request $request): array
    {
        $filters = $request->only([
            'fecha_desde', 'fecha_hasta', 'campo_fecha', 'estado', 'situacion', 'servicio',
            'producto_id', 'modulo_id', 'solo_deudores', 'buscar',
        ]);
        if (!empty($filters['producto_id'])) {
            $filters['producto'] = Producto::withTrashed()->find($filters['producto_id'])?->nombre ?? $filters['producto_id'];
            unset($filters['producto_id']);
        }
        if (!empty($filters['modulo_id'])) {
            $filters['modulo'] = Modulo::withTrashed()->find($filters['modulo_id'])?->nombre ?? $filters['modulo_id'];
            unset($filters['modulo_id']);
        }

        return array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== false);
    }
}
