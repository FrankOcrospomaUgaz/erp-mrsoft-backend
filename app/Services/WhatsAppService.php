<?php

namespace App\Services;

use App\Models\Comprobante;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    protected string $token;
    protected string $phoneNumberId;
    protected string $version;
    protected string $baseUrl;
    protected string $templateName;

    public function __construct()
    {
        $this->token = (string) config('services.whatsapp.token');
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $this->version = (string) config('services.whatsapp.version', 'v19.0');
        $this->baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $this->templateName = (string) config('services.whatsapp.template_name', 'hotelhub_pdf_invoice');
    }

    /**
     * Normalizar número de teléfono (remueve caracteres no numéricos y agrega prefijo si falta)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Si es un celular peruano de 9 dígitos sin prefijo (empieza con 9)
        if (strlen($cleaned) === 9 && str_starts_with($cleaned, '9')) {
            return '51' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Subir archivo PDF a Meta WhatsApp Media API
     */
    public function uploadPdfMedia(string $pdfBinary, string $filename): string
    {
        if (empty($this->token)) {
            throw new Exception('Token de Meta WhatsApp API (META_AUTH_TOKEN) no configurado en el servidor.');
        }

        $endpoint = "{$this->baseUrl}/{$this->version}/{$this->phoneNumberId}/media";

        $response = Http::withToken($this->token)
            ->attach('file', $pdfBinary, $filename, ['Content-Type' => 'application/pdf'])
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
            ]);

        if (!$response->successful()) {
            $errorMsg = $response->json('error.message') ?? $response->body();
            Log::error('Error al subir PDF a WhatsApp Media API', ['status' => $response->status(), 'response' => $response->body()]);
            throw new Exception('Fallo al subir PDF a WhatsApp: ' . $errorMsg);
        }

        $mediaId = $response->json('id');
        if (empty($mediaId)) {
            throw new Exception('Meta WhatsApp API no devolvió un ID de media válido.');
        }

        return $mediaId;
    }

    /**
     * Enviar comprobante por WhatsApp (intenta plantilla Meta o documento directo como respaldo)
     */
    public function enviarComprobante(Comprobante $comprobante, string $pdfBinary, string $celular): array
    {
        try {
            $telefono = $this->formatPhoneNumber($celular);

            if (empty($telefono)) {
                return [
                    'success' => false,
                    'error' => 'El número de teléfono proporcionado no es válido.',
                ];
            }

            $numeroComprobante = $comprobante->serie . '-' . str_pad((string) $comprobante->correlativo, 6, '0', STR_PAD_LEFT);
            $filename = 'comprobante-' . $numeroComprobante . '.pdf';

            // 1. Subir PDF a Meta
            $mediaId = $this->uploadPdfMedia($pdfBinary, $filename);

            // 2. Preparar variables para la plantilla de Meta
            $clienteModel = $comprobante->cliente;
            $nombreCliente = strtoupper($clienteModel?->razon_social ?? $clienteModel?->nombre_comercial ?? 'CLIENTE');
            $docCliente = $clienteModel?->ruc ?? $clienteModel?->contactos_clientes?->first()?->dni ?? '';
            $clienteTexto = trim($docCliente . ' - ' . $nombreCliente);

            $facturador = $comprobante->facturador ?? \App\Models\Facturador::where('activo', true)->first();
            $razonSocialEmisor = strtoupper($facturador?->razon_social ?? 'MRSOFT');
            $nombreComercialEmisor = strtoupper($facturador?->nombre_comercial ?? $razonSocialEmisor);
            $firma = 'MrSoft ERP';
            $downloadBillUrl = url('/api/comprobantes/' . $comprobante->id . '/pdf');

            $endpoint = "{$this->baseUrl}/{$this->version}/{$this->phoneNumberId}/messages";

            // Intentar envío por Template hotelhub_pdf_invoice primero
            $templatePayload = [
                'messaging_product' => 'whatsapp',
                'to' => $telefono,
                'type' => 'template',
                'template' => [
                    'name' => $this->templateName,
                    'language' => ['code' => 'es'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'id' => $mediaId,
                                        'filename' => $filename,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $clienteTexto],
                                ['type' => 'text', 'text' => $numeroComprobante],
                                ['type' => 'text', 'text' => $razonSocialEmisor],
                                ['type' => 'text', 'text' => $nombreComercialEmisor],
                                ['type' => 'text', 'text' => $firma],
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($this->token)
                ->post($endpoint, $templatePayload);

            // Si la plantilla falla o no existe en la cuenta de Meta del usuario, usar respaldo por mensaje de documento directo
            if (!$response->successful()) {
                Log::warning('Respuesta plantilla WhatsApp no exitosa, intentando respaldo con tipo document', ['response' => $response->body()]);

                $documentPayload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $telefono,
                    'type' => 'document',
                    'document' => [
                        'id' => $mediaId,
                        'filename' => $filename,
                        'caption' => "Estimado(a) {$nombreCliente}, adjuntamos su comprobante electrónico {$numeroComprobante} emitido por {$razonSocialEmisor}.",
                    ],
                ];

                $response = Http::withToken($this->token)
                    ->post($endpoint, $documentPayload);
            }

            if ($response->successful() && isset($response->json()['messages'])) {
                return [
                    'success' => true,
                    'message' => 'Comprobante enviado exitosamente por WhatsApp.',
                    'whatsapp_id' => $response->json('messages.0.id'),
                ];
            }

            $errorMsg = $response->json('error.message') ?? $response->body();
            return [
                'success' => false,
                'error' => 'Mensaje no aceptado por WhatsApp: ' . $errorMsg,
            ];
        } catch (Exception $e) {
            Log::error('Excepción al enviar WhatsApp de comprobante', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
