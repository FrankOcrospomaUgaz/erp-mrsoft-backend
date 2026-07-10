<?php

return [
    'modo' => env('FACTURACION_MODO', 'simulacion'),
    'ruc' => env('FACTURACION_RUC'),
    'razon_social' => env('FACTURACION_RAZON_SOCIAL', 'Mr. Soft ERP System'),
    'nombre_comercial' => env('FACTURACION_NOMBRE_COMERCIAL', 'Mr. Soft'),
    'direccion' => env('FACTURACION_DIRECCION'),
    'usuario_sol' => env('FACTURACION_USUARIO_SOL'),
    'clave_sol' => env('FACTURACION_CLAVE_SOL'),
    'token' => env('FACTURACION_TOKEN'),
    'porcentaje_igv' => (float) env('FACTURACION_IGV', 18),
    'wsdl' => [
        'factura' => env('FACTURACION_WSDL_FACTURA', 'http://157.245.85.164/facturacion/wsdl/wsdl_factura_rc.php'),
        'boleta' => env('FACTURACION_WSDL_BOLETA', 'http://157.245.85.164/facturacion/wsdl/wsdl_boleta_rc.php'),
        'consulta' => env('FACTURACION_WSDL_CONSULTA', 'http://157.245.85.164/facturacion/wsdl/wsdl_comprobantes.php'),
        'bajas' => env('FACTURACION_WSDL_BAJAS', 'http://157.245.85.164/facturacion/wsdl/wsdl_comunicacionbajas.php'),
    ],
];
