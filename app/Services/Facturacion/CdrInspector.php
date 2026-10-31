<?php

namespace App\Services\Facturacion;

use SimpleXMLElement;
use ZipArchive;

class CdrInspector
{
    public static function contieneAceptacion(string $zipBinary, string $serie, int $correlativo): bool
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            return false;
        }

        $tempFile = tmpfile();
        if ($tempFile === false) {
            return false;
        }

        $metadata = stream_get_meta_data($tempFile);
        fwrite($tempFile, $zipBinary);
        fflush($tempFile);

        $zip = new ZipArchive();
        if ($zip->open($metadata['uri']) !== true) {
            fclose($tempFile);
            return false;
        }

        try {
            $documentoEsperado = self::normalizarReferencia($serie . '-' . $correlativo);

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $nombre = $zip->getNameIndex($index);
                if (!is_string($nombre) || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'xml') {
                    continue;
                }

                $contenido = $zip->getFromIndex($index);
                if (!is_string($contenido)) {
                    continue;
                }

                $xml = self::parsearCdr($contenido);
                if (!$xml) {
                    continue;
                }

                $respuestas = $xml->xpath('//*[local-name()="DocumentResponse"]/*[local-name()="Response"]') ?: [];
                foreach ($respuestas as $respuesta) {
                    $codigos = $respuesta->xpath('./*[local-name()="ResponseCode"]') ?: [];
                    $referencias = $respuesta->xpath('./*[local-name()="ReferenceID"]') ?: [];

                    if (trim((string) ($codigos[0] ?? '')) !== '0') {
                        continue;
                    }

                    foreach ($referencias as $referencia) {
                        if (self::normalizarReferencia((string) $referencia) === $documentoEsperado) {
                            return true;
                        }
                    }
                }
            }

            return false;
        } finally {
            $zip->close();
            fclose($tempFile);
        }
    }

    private static function parsearCdr(string $contenido): ?SimpleXMLElement
    {
        $usoPrevioErroresLibxml = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contenido, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

            return $xml instanceof SimpleXMLElement && $xml->getName() === 'ApplicationResponse'
                ? $xml
                : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($usoPrevioErroresLibxml);
        }
    }

    private static function normalizarReferencia(string $referencia): string
    {
        if (!preg_match('/^([A-Z0-9]+)-(\d+)$/i', trim($referencia), $partes)) {
            return strtoupper(trim($referencia));
        }

        $correlativo = ltrim($partes[2], '0');

        return strtoupper($partes[1]) . '-' . ($correlativo === '' ? '0' : $correlativo);
    }
}
