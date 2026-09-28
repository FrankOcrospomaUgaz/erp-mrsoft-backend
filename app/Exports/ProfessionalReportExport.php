<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProfessionalReportExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(private readonly array $report, private readonly array $filters = []) {}

    public function array(): array
    {
        $columnCount = count($this->report['columns']);
        $rows = [
            array_pad([$this->report['title']], $columnCount, null),
            array_pad([$this->report['description']], $columnCount, null),
            array_pad(['Generado: '.$this->report['generated_at'].' | Filtros: '.$this->filterText()], $columnCount, null),
            array_fill(0, $columnCount, null),
            $this->report['columns'],
        ];

        foreach ($this->report['rows'] as $row) $rows[] = $row;

        $rows[] = array_fill(0, $columnCount, null);
        $rows[] = array_pad(['RESUMEN EJECUTIVO'], $columnCount, null);
        foreach ($this->report['summary'] as $key => $value) {
            $rows[] = array_pad([Str::headline($key), is_float($value) ? round($value, 2) : $value], $columnCount, null);
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('~[\\\\/?*\[\]:]~', '', $this->report['title']), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $dataEnd = 5 + count($this->report['rows']);
                $summaryTitleRow = $dataEnd + 2;

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->mergeCells("A{$summaryTitleRow}:{$lastColumn}{$summaryTitleRow}");
                $sheet->freezePane('A6');
                if ($dataEnd >= 6) $sheet->setAutoFilter("A5:{$lastColumn}{$dataEnd}");

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173B63']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->getStyle("A2:{$lastColumn}3")->applyFromArray([
                    'font' => ['color' => ['rgb' => '52677E']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF1F8']],
                    'alignment' => ['wrapText' => true],
                ]);
                $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '167CA6']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8C8D8']]],
                ]);
                $sheet->getRowDimension(5)->setRowHeight(28);

                if ($dataEnd >= 6) {
                    $sheet->getStyle("A6:{$lastColumn}{$dataEnd}")->applyFromArray([
                        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D9E2EC']]],
                    ]);
                    for ($row = 6; $row <= $dataEnd; $row++) {
                        if ($row % 2 === 0) $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4F8FB');
                    }
                }

                foreach ($this->report['currencyColumns'] as $index) {
                    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->getStyle("{$column}6:{$column}{$dataEnd}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                }

                $sheet->getStyle("A{$summaryTitleRow}:{$lastColumn}{$summaryTitleRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173B63']],
                ]);
                $sheet->getStyle("A".($summaryTitleRow + 1).":B{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF1F8']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C8D6E5']]],
                ]);

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);
                $sheet->getHeaderFooter()->setOddFooter('&LMRSoft ERP&C&Página &P de &N');
            },
        ];
    }

    private function filterText(): string
    {
        $labels = [
            'fecha_desde' => 'Desde', 'fecha_hasta' => 'Hasta', 'campo_fecha' => 'Fecha',
            'estado' => 'Estado', 'situacion' => 'Situación', 'servicio' => 'Servicio',
            'producto_id' => 'Producto ID', 'modulo_id' => 'Módulo ID', 'solo_deudores' => 'Solo deudores', 'buscar' => 'Búsqueda',
        ];
        $active = collect($this->filters)->filter(fn ($value) => $value !== null && $value !== '' && $value !== false)
            ->map(fn ($value, $key) => ($labels[$key] ?? Str::headline($key)).': '.($value === true ? 'Sí' : $value));
        return $active->isEmpty() ? 'Todos los registros' : $active->implode(' · ');
    }
}
