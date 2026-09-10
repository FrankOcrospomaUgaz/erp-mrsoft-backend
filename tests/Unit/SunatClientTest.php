<?php

namespace Tests\Unit;

use App\Services\Facturacion\SunatClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SunatClientTest extends TestCase
{
    public function test_normaliza_una_respuesta_exitosa_del_wsdl(): void
    {
        $client = new SunatClient();
        $method = new ReflectionMethod($client, 'normalizarRespuesta');
        $method->setAccessible(true);

        $respuesta = $method->invoke($client, json_encode([
            'code' => 0,
            'mensaje' => 'Aceptado',
            'fileZIPBASE64' => base64_encode('zip'),
        ]));

        $this->assertTrue($respuesta['ok']);
        $this->assertSame(0, $respuesta['code']);
        $this->assertSame('Aceptado', $respuesta['mensaje']);
    }

    public function test_no_acepta_una_respuesta_invalida(): void
    {
        $client = new SunatClient();
        $method = new ReflectionMethod($client, 'normalizarRespuesta');
        $method->setAccessible(true);

        $respuesta = $method->invoke($client, '<html>Error</html>');

        $this->assertFalse($respuesta['ok']);
        $this->assertSame('RESPUESTA_INVALIDA', $respuesta['code']);
    }
}
