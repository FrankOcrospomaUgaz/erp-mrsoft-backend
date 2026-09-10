<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteDetalle;
use App\Models\Facturador;
use App\Services\Facturacion\ComprobanteService;
use App\Services\Facturacion\SunatClient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ComprobanteServiceTest extends TestCase
{
    public function test_desglosa_el_igv_sin_incrementar_el_precio_final(): void
    {
        $service = new ComprobanteService(new SunatClient());
        $method = new ReflectionMethod($service, 'calcularTotales');
        $method->setAccessible(true);

        $resultado = $method->invoke($service, [[
            'descripcion' => 'Cuota mensual',
            'cantidad' => 1,
            'precio_unitario' => 118,
            'tipo_igv' => '10',
            'unidad' => 'NIU',
        ]], 18.0);

        $this->assertSame(100.0, $resultado['subtotal']);
        $this->assertSame(18.0, $resultado['igv']);
        $this->assertSame(118.0, $resultado['total']);
        $this->assertSame(118.0, $resultado['detalles'][0]['total']);
    }

    public function test_no_desglosa_igv_en_un_detalle_inafecto(): void
    {
        $service = new ComprobanteService(new SunatClient());
        $method = new ReflectionMethod($service, 'calcularTotales');
        $method->setAccessible(true);

        $resultado = $method->invoke($service, [[
            'descripcion' => 'Concepto inafecto',
            'cantidad' => 2,
            'precio_unitario' => 50,
            'tipo_igv' => '30',
            'unidad' => 'NIU',
        ]], 18.0);

        $this->assertSame(100.0, $resultado['subtotal']);
        $this->assertSame(0.0, $resultado['igv']);
        $this->assertSame(100.0, $resultado['total']);
    }

    public function test_construye_el_sobre_exacto_requerido_por_el_wsdl(): void
    {
        $cliente = (new Cliente())->forceFill([
            'ruc' => '20678912345',
            'razon_social' => 'Tecnologias Andinas SAC',
            'direccion' => 'Lima',
        ]);
        $cliente->setRelation('parent_cliente', null);
        $cliente->setRelation('contactos_clientes', new Collection());

        $detalle = (new ComprobanteDetalle())->forceFill([
            'descripcion' => 'Servicio',
            'cantidad' => 1,
            'precio_unitario' => 118,
            'tipo_igv' => '10',
            'unidad' => 'NIU',
        ]);

        $comprobante = (new Comprobante())->forceFill([
            'tipo_documento' => 'F',
            'serie' => 'F001',
            'correlativo' => 3,
            'fecha_emision' => Carbon::parse('2026-09-10'),
            'hora_emision' => '10:00:00',
            'moneda' => 'PEN',
            'forma_pago' => 'C',
            'total' => 118,
        ]);
        $comprobante->setRelation('cliente', $cliente);
        $comprobante->setRelation('detalles', new Collection([$detalle]));

        $facturador = (new Facturador())->forceFill(['token' => null]);
        $service = new ComprobanteService(new SunatClient());
        $method = new ReflectionMethod($service, 'armarPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($service, $comprobante, $facturador);
        $documento = json_decode($payload['comprobante'], true);

        $this->assertSame('F001', $payload['seriefactura']);
        $this->assertSame('3', $payload['correlativofactura']);
        $this->assertSame('20678912345', $payload['doc']);
        $this->assertSame('F001-000003', $documento['numerofactura']);
        $this->assertEquals(118.0, $documento['detalles'][0]['precioventaunitarioxitem']);
        $this->assertArrayHasKey('tasaisc', $documento['detalles'][0]);
    }
}
