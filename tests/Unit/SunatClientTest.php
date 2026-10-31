<?php

namespace Tests\Unit;

use App\Services\Facturacion\SunatClient;
use App\Models\Facturador;
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

    public function test_no_confunde_error_de_consulta_con_duplicado_y_oculta_credenciales(): void
    {
        $client = new SunatClient();
        $method = new ReflectionMethod($client, 'normalizarRespuesta');
        $method->setAccessible(true);

        $respuesta = $method->invoke($client, [
            'code' => 9,
            'mensaje' => 'LA CONTRASEÑA NO ES CORRECTA. [20602871119,clave-prueba-privada]',
            'file' => 'wsdl_factura_rc.php',
            'line' => 43,
        ], ['20602871119', 'clave-prueba-privada']);

        $this->assertStringContainsString('LA CONTRASEÑA NO ES CORRECTA', $respuesta['mensaje']);
        $this->assertStringNotContainsString('clave-prueba-privada', json_encode($respuesta));
        $this->assertStringNotContainsString('20602871119', json_encode($respuesta));
        $this->assertStringNotContainsString('no permite duplicarlo', $respuesta['mensaje']);
    }

    public function test_un_error_generico_de_consulta_no_afirma_que_la_serie_este_duplicada(): void
    {
        $client = new SunatClient();
        $method = new ReflectionMethod($client, 'normalizarRespuesta');
        $method->setAccessible(true);

        $respuesta = $method->invoke($client, [
            'code' => 9,
            'mensaje' => 'ERROR EN LA CONSULTA',
        ]);

        $this->assertStringNotContainsString('no permite duplicarlo', $respuesta['mensaje']);
    }

    public function test_autentica_con_el_usuario_wsdl_y_no_con_el_ruc_emisor(): void
    {
        $facturador = (new Facturador())->forceFill([
            'ruc' => '20600000000',
            'usuario_sol' => 'usuario-wsdl',
            'clave_sol' => 'clave-wsdl',
        ]);
        $client = new SunatClient();
        $method = new ReflectionMethod($client, 'credencialesWsdl');
        $method->setAccessible(true);

        $this->assertSame(['usuario-wsdl', 'clave-wsdl'], $method->invoke($client, $facturador));
    }
}
