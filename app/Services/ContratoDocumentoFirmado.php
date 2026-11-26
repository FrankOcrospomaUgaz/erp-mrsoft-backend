<?php

namespace App\Services;

use App\Models\ContratoDocumento;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use setasign\Fpdi\Fpdi;

class ContratoDocumentoFirmado
{
    /**
     * Builds a temporary signed copy for supported uploaded contract formats.
     * The source document remains unchanged in storage.
     *
     * @return array{path:string,filename:string,mime_type:string}|null
     */
    public function crearCopia(ContratoDocumento $documento): ?array
    {
        $contract = $documento->contrato;
        $signature = $contract?->firma_cliente;
        $sourcePath = Storage::disk('public')->path($documento->ruta);
        $extension = strtolower(pathinfo($documento->nombre_original, PATHINFO_EXTENSION));

        if (!$signature || !is_file($sourcePath)) {
            return null;
        }

        $signaturePath = $this->guardarFirmaTemporal($signature);
        if (!$signaturePath) {
            return null;
        }

        try {
            if ($extension === 'pdf') {
                $outputPath = $this->firmarPdf($sourcePath, $signaturePath);
                return [
                    'path' => $outputPath,
                    'filename' => pathinfo($documento->nombre_original, PATHINFO_FILENAME) . '-firmado.pdf',
                    'mime_type' => 'application/pdf',
                ];
            }

            if ($extension === 'docx') {
                $outputPath = $this->firmarWord($sourcePath, $signaturePath);
                return [
                    'path' => $outputPath,
                    'filename' => pathinfo($documento->nombre_original, PATHINFO_FILENAME) . '-firmado.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
            }

            return null;
        } finally {
            @unlink($signaturePath);
        }
    }

    private function firmarPdf(string $sourcePath, string $signaturePath): string
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($pageNumber === $pageCount) {
                $signatureWidth = min(54, $size['width'] * 0.30);
                $signatureHeight = 20;
                // The uploaded contract already contains the client name and signature area.
                // Place only the signature above the client's name; do not add another line or label.
                $x = ($size['width'] * 0.75) - ($signatureWidth / 2);
                $y = $size['height'] - 96;

                $pdf->Image($signaturePath, $x, $y, $signatureWidth, $signatureHeight);
            }
        }

        $outputPath = $this->temporaryPath('pdf');
        $pdf->Output('F', $outputPath);
        return $outputPath;
    }

    private function firmarWord(string $sourcePath, string $signaturePath): string
    {
        $document = IOFactory::load($sourcePath);
        $sections = $document->getSections();
        $section = $sections ? $sections[array_key_last($sections)] : $document->addSection();

        // Add a dedicated final signature area so the uploaded contract's existing text is preserved.
        $section->addPageBreak();
        $section->addText('FIRMA DEL CLIENTE', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(2);
        $section->addImage($signaturePath, [
            'width' => 190,
            'height' => 75,
            'alignment' => Jc::CENTER,
        ]);
        $section->addText('________________________________________', [], ['alignment' => Jc::CENTER]);
        $section->addText('EL CLIENTE', ['bold' => true], ['alignment' => Jc::CENTER]);

        $outputPath = $this->temporaryPath('docx');
        IOFactory::createWriter($document, 'Word2007')->save($outputPath);
        return $outputPath;
    }

    private function guardarFirmaTemporal(string $signature): ?string
    {
        if (!preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/s', $signature, $matches)) {
            return null;
        }

        $data = base64_decode($matches[2], true);
        if ($data === false) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $path = $this->temporaryPath($extension);
        file_put_contents($path, $data);
        return $path;
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'contrato_firmado_');
        @unlink($base);
        return $base . '.' . $extension;
    }
}
