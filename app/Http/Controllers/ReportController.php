<?php

namespace App\Http\Controllers;

use App\Exports\ProfessionalReportExport;
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
        return Excel::download(new ProfessionalReportExport($report, $request->only([
            'fecha_desde', 'fecha_hasta', 'campo_fecha', 'estado', 'situacion', 'servicio',
            'producto_id', 'modulo_id', 'solo_deudores', 'buscar',
        ])), $filename);
    }

    public function pdf(Request $request, string $type)
    {
        $report = $this->reports->make($type, $request);
        $pdf = Pdf::loadView('pdf.report', [
            'report' => $report,
            'filters' => $request->only(['fecha_desde', 'fecha_hasta', 'campo_fecha', 'estado', 'situacion', 'servicio', 'solo_deudores', 'buscar']),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream(Str::slug($report['title']).'-'.now()->format('Ymd-His').'.pdf');
    }
}
