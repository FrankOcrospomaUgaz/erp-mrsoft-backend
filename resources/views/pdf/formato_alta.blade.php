@php
    $formato = $producto->formato_alta ?? [];
    $htmlContent = $formato['html_content'] ?? null;
@endphp

@if(!empty($htmlContent))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de Alta - {{ $producto->nombre }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            background: #ffffff;
        }
        .a4-page-sheet {
            width: 100%;
            height: 100%;
            position: relative;
            background: #ffffff;
            box-sizing: border-box;
            page-break-after: always;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .a4-page-sheet:last-child {
            page-break-after: avoid;
        }
        h1, h2, h3 {
            color: #eb5454;
            font-family: Helvetica, Arial, sans-serif;
            font-weight: 700;
            text-transform: uppercase;
        }
        h1 { font-size: 14pt; margin: 14pt 0 8pt 0; }
        h2 { font-size: 12pt; margin: 12pt 0 6pt 0; }
        h3 { font-size: 10pt; margin: 10pt 0 4pt 0; }
        p {
            margin: 0 0 8px 0;
            line-height: 1.55;
            font-size: 12px;
        }
        ul {
            list-style-type: disc !important;
            margin: 8px 0 14px 24px !important;
            padding-left: 10px !important;
        }
        li {
            display: list-item !important;
            list-style-type: disc !important;
            margin: 4px 0 !important;
            font-size: 12px !important;
            line-height: 1.55 !important;
        }
        table {
            border-collapse: collapse;
        }
        td[style*="#eb5454"],
        td[style*="235, 84, 84"],
        th {
            color: #ffffff !important;
        }
        td[style*="#eb5454"] *,
        td[style*="235, 84, 84"] *,
        th * {
            color: #ffffff !important;
        }
    </style>
</head>
<body>
    {!! $htmlContent !!}
</body>
</html>
@else
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de Alta - {{ $producto->nombre }}</title>
    <style>
        @page {
            margin: 30px 45px 40px 45px;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #2b2b2b;
            font-size: 11.5px;
            line-height: 1.35;
        }
        .page-break {
            page-break-after: always;
        }
        .text-coral {
            color: #eb5454;
        }
        .header-logo {
            text-align: right;
            margin-bottom: 20px;
        }
        .header-logo img {
            max-height: 38px;
        }
        .header-logo-text {
            font-size: 16px;
            font-weight: 700;
            color: #eb5454;
        }
        .header-logo-sub {
            font-size: 9px;
            color: #888;
        }
        .section-title {
            color: #eb5454;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
            margin-bottom: 18px;
        }
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 25px;
            border-top: 1px solid #ddd;
            padding-top: 5px;
            font-size: 10px;
            color: #777;
        }
        .footer-left {
            float: left;
        }
        .footer-right {
            float: right;
        }

        /* Tablas de Credenciales y Series */
        .cred-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 20px 0;
        }
        .cred-table th {
            background-color: #eb5454;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            text-align: center;
            border: 1px solid #eb5454;
        }
        .cred-table td {
            border: 1px solid #ddd;
            padding: 6px 12px;
            font-size: 11px;
            text-align: center;
        }
        .cred-table tr:nth-child(even) {
            background-color: #fafafa;
        }
        .cred-table-left td {
            text-align: left;
        }
        .cred-table-left td.val {
            text-align: center;
            font-weight: 600;
        }

        /* Tabla de Tutoriales */
        .tut-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10.5px;
        }
        .tut-table th {
            background-color: #eb5454;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 10px;
            text-align: center;
            border: 1px solid #eb5454;
        }
        .tut-table td {
            border: 1px solid #e2e2e2;
            padding: 5px 8px;
            vertical-align: middle;
        }
        .tut-table tr:nth-child(even) {
            background-color: #fdfdfd;
        }
        .tut-link {
            color: #eb5454;
            text-decoration: underline;
            word-break: break-all;
            font-size: 10px;
        }

        /* Portada */
        .cover-page {
            height: 92%;
            position: relative;
            padding-top: 40px;
        }
        .cover-logo-box {
            text-align: right;
            padding-right: 20px;
        }
        .cover-brand-title {
            font-size: 32px;
            font-weight: bold;
            color: #eb5454;
            margin: 0;
            line-height: 1;
        }
        .cover-brand-subtitle {
            font-size: 13px;
            color: #eb5454;
            margin-top: 4px;
            font-weight: 500;
        }
        .cover-contact {
            font-size: 11px;
            color: #555;
            margin-top: 12px;
            line-height: 1.5;
        }
        .cover-contact a {
            color: #0b4e8c;
            text-decoration: underline;
        }
        .cover-footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
        }
        .cover-footer-left {
            float: left;
        }
        .cover-footer-right {
            float: right;
            color: #eb5454;
            font-weight: 600;
            font-size: 13px;
            padding-top: 8px;
        }
        .cover-dev-logo {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .cover-dev-sub {
            font-size: 10px;
            color: #0088cc;
            letter-spacing: 1px;
        }

        /* Viñetas */
        ul.bullet-list {
            margin: 10px 0 16px 15px;
            padding-left: 10px;
        }
        ul.bullet-list li {
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .channel-info-box {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px 0;
        }
        .channel-info-box th {
            background-color: #eb5454;
            color: #fff;
            padding: 6px 10px;
            font-weight: bold;
            width: 25%;
            text-align: left;
            border: 1px solid #eb5454;
        }
        .channel-info-box td {
            background-color: #fafafa;
            padding: 6px 10px;
            border: 1px solid #ddd;
        }

        .signature-section {
            margin-top: 45px;
            text-align: center;
        }
        .signature-line {
            width: 260px;
            border-top: 1px solid #333;
            margin: 0 auto 6px auto;
        }
        .signature-name {
            font-weight: bold;
            font-size: 12px;
            color: #111;
        }
        .signature-title {
            font-size: 11px;
            font-weight: bold;
            color: #444;
        }

        .access-card {
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 15px;
            background-color: #fafafa;
            margin: 15px 0;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    $formato = $producto->formato_alta ?? [];
    $portada = $formato['portada'] ?? [];
    $presentacion = $formato['presentacion'] ?? [];
    $acceso = $formato['acceso'] ?? [];
    $facturacion = $formato['facturacion'] ?? [];
    $tutoriales = $formato['tutoriales'] ?? [];

    $slogan = $portada['slogan'] ?? 'Tu restaurante digital';
    $telefonoSoporte = $portada['telefono_soporte'] ?? '+51 979 293 176';
    $emailSoporte = $portada['email_soporte'] ?? 'martin.ampuero@garzasoft.com';
    $webUrl = $portada['web_url'] ?? 'www.gesrest.net';
    $devLogo = $portada['empresa_desarrollo'] ?? 'Mr. Soft';

    $nombreProducto = strtoupper($producto->nombre);
    $clienteRazonSocial = $cliente->razon_social ?? $cliente->nombre_comercial ?? ($datosCliente['razon_social'] ?? 'EMPRESA CLIENTE');
    $clienteRuc = $cliente->ruc ?? ($datosCliente['ruc'] ?? '20601799317');
    $htmlContent = $formato['html_content'] ?? null;
@endphp

<!-- PÁGINA 1: PORTADA -->
<div class="cover-page">
    <div class="cover-logo-box">
        <div class="cover-brand-title">{{ $producto->nombre }}</div>
        <div class="cover-brand-subtitle">{{ $slogan }}</div>
        <div class="cover-contact">
            <div>{{ $telefonoSoporte }}</div>
            <div><a href="mailto:{{ $emailSoporte }}">{{ $emailSoporte }}</a></div>
        </div>
    </div>

    <div class="cover-footer">
        <div class="cover-footer-left">
            <div class="cover-dev-logo">{{ $devLogo }}</div>
            <div class="cover-dev-sub">Development</div>
        </div>
        <div class="cover-footer-right">
            {{ $webUrl }}
        </div>
    </div>
</div>
<div class="page-break"></div>

<!-- PÁGINA 2: PRESENTACIÓN -->
<div class="header-logo">
    <span class="header-logo-text">{{ $producto->nombre }}</span><br>
    <span class="header-logo-sub">{{ $slogan }}</span>
</div>

<div class="section-title">{{ $presentacion['titulo'] ?? 'PRESENTACIÓN' }}</div>

<p style="margin-bottom: 12px; font-size: 11.5px; line-height: 1.45;">
    {{ $presentacion['descripcion'] ?? "$nombreProducto es el software en nube para gestión empresarial y comercial. Incluye atención a clientes, control de inventario, seguimiento de ingresos y egresos, compras y cuentas por pagar, y sincronización con facturación electrónica." }}
</p>

<p style="font-weight: 600; margin-top: 14px; margin-bottom: 6px;">
    {{ $nombreProducto }} es la herramienta ideal si necesitas conocer:
</p>

<ul class="bullet-list">
    @php
        $caracteristicas = $presentacion['caracteristicas'] ?? [
            'Detalle de los productos que vendes.',
            'Detalle de los productos compras.',
            'Detalle de los productos en tu almacén.',
            'El personal responsable de cada operación en tu negocio.',
            'El importe total y detalle de dinero en caja diaria.',
            'Detalle de gastos.',
            'El tiempo de atención / preparación de cocina y bar.',
            'La estadística de venta de platos en el restaurante.',
            'La productividad por mesero, plato, turno, salón, otros.'
        ];
    @endphp
    @foreach($caracteristicas as $caract)
        <li>{{ $caract }}</li>
    @endforeach
</ul>

<p style="margin-top: 20px; line-height: 1.45;">
    {{ $presentacion['mensaje_agradecimiento'] ?? "Mr. SOFT agradece depositar su confianza en nuestra empresa, le garantizamos el soporte y apoyo necesario para aprovechar al máximo $nombreProducto, nuestra herramienta para su productividad." }}
</p>

<div class="signature-section">
    <div style="height: 40px;"></div>
    <div class="signature-line"></div>
    <div class="signature-name">{{ $presentacion['firmante_nombre'] ?? 'Gilberto Martín Ampuero Pasco' }}</div>
    <div class="signature-title">{{ $presentacion['firmante_cargo'] ?? 'CEO Mr. SOFT' }}</div>
</div>

<div class="footer">
    <div class="footer-left">Un producto de Mr. Soft</div>
    <div class="footer-right">1</div>
</div>
<div class="page-break"></div>

<!-- PÁGINA 3: CREDENCIALES DE ACCESO -->
<div class="header-logo">
    <span class="header-logo-text">{{ $producto->nombre }}</span><br>
    <span class="header-logo-sub">{{ $slogan }}</span>
</div>

<div class="section-title">{{ $acceso['titulo'] ?? 'CREDENCIALES DE ACCESO' }}</div>

<p style="font-size: 11.5px; line-height: 1.45;">
    Para utilizar los servicios de nuestra plataforma <strong>{{ $producto->nombre }}</strong> debe ingresar al enlace:
</p>
<p style="margin: 8px 0; color: #eb5454; font-weight: bold; font-size: 12px;">
    {{ $acceso['url_acceso'] ?? 'https://gesrest.net/' }}
</p>
<p style="font-size: 11.5px; line-height: 1.45; margin-bottom: 25px;">
    y luego presionar el botón <strong>"LOGIN"</strong> para registrar sus credenciales de acceso.
</p>

<div class="access-card">
    <div style="font-size: 18px; font-weight: bold; color: #eb5454; margin-bottom: 8px;">
        {{ $producto->nombre }}
    </div>
    <div style="font-size: 12px; color: #555; margin-bottom: 12px;">
        El seguimiento de tus ventas, con solo un clic!
    </div>
    <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 6px; display: inline-block; width: 80%;">
        <div style="text-align: left; font-size: 10px; color: #777; margin-bottom: 4px;">Nombre de usuario: [ RUC o Usuario ]</div>
        <div style="border: 1px solid #ccc; background: #fdfdfd; height: 22px; margin-bottom: 10px; border-radius: 4px;"></div>
        <div style="text-align: left; font-size: 10px; color: #777; margin-bottom: 4px;">Contraseña: [ Clave Asignada ]</div>
        <div style="border: 1px solid #ccc; background: #fdfdfd; height: 22px; margin-bottom: 14px; border-radius: 4px;"></div>
        <div style="background: #eb5454; color: #fff; font-weight: bold; padding: 6px; border-radius: 4px; font-size: 11px;">
            INICIAR SESIÓN
        </div>
    </div>
</div>

<div class="footer">
    <div class="footer-left">Un producto de Mr. Soft</div>
    <div class="footer-right">2</div>
</div>
<div class="page-break"></div>

<!-- PÁGINA 4: PERFILES DE USUARIOS -->
<div class="header-logo">
    <span class="header-logo-text">{{ $producto->nombre }}</span><br>
    <span class="header-logo-sub">{{ $slogan }}</span>
</div>

@php
    $perfiles = $acceso['perfiles'] ?? [
        [
            'perfil' => 'PERFIL ADMINISTRADOR',
            'usuarios' => [
                ['usuario' => $clienteRuc, 'clave' => $clienteRuc]
            ]
        ],
        [
            'perfil' => 'PERFIL CAJERO',
            'usuarios' => [
                ['usuario' => 'CAJERO1', 'clave' => 'CAJERO1'],
                ['usuario' => 'CAJERO2', 'clave' => 'CAJERO2']
            ]
        ],
        [
            'perfil' => 'PERFIL MESERO / OPERATIVO',
            'enlace' => $acceso['url_mesero'] ?? 'https://sistema.gesrest.net/waiter-login',
            'usuarios' => [
                ['usuario' => 'MESERO1', 'clave' => '1234'],
                ['usuario' => 'MESERO2', 'clave' => '1234'],
                ['usuario' => 'MESERO3', 'clave' => '1234'],
            ]
        ]
    ];
@endphp

@foreach($perfiles as $p)
    <div style="color: #eb5454; font-weight: bold; font-size: 12px; margin-top: 15px; margin-bottom: 4px; text-transform: uppercase;">
        {{ $p['perfil'] }}
    </div>

    @if(!empty($p['enlace']))
        <p style="font-size: 10.5px; margin-bottom: 6px;">
            Enlace para credenciales: <a href="{{ $p['enlace'] }}" style="color: #0b4e8c; text-decoration: underline;">{{ $p['enlace'] }}</a>
        </p>
    @endif

    <table class="cred-table">
        <thead>
            <tr>
                <th style="width: 50%;">Usuario</th>
                <th style="width: 50%;">Clave</th>
            </tr>
        </thead>
        <tbody>
            @foreach($p['usuarios'] ?? [] as $u)
                <tr>
                    <td style="font-weight: 600;">{{ $u['usuario'] }}</td>
                    <td style="font-weight: 600;">{{ $u['clave'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

<div class="footer">
    <div class="footer-left">Un producto de Mr. Soft</div>
    <div class="footer-right">3</div>
</div>
<div class="page-break"></div>

<!-- PÁGINA 5: PORTAL DE CONTADOR Y FACTURACIÓN -->
<div class="header-logo">
    <span class="header-logo-text">{{ $producto->nombre }}</span><br>
    <span class="header-logo-sub">{{ $slogan }}</span>
</div>

<div class="section-title">{{ $facturacion['titulo'] ?? 'CREDENCIALES PARA ACCESO A PORTAL DE CONTADOR' }}</div>

<p style="font-size: 11.5px; line-height: 1.45;">
    Para utilizar los servicios de nuestro portal de facturación electrónica debe ingresar al enlace:
</p>
<p style="margin: 8px 0; color: #eb5454; font-weight: bold; font-size: 12px;">
    {{ $facturacion['url_portal'] ?? 'https://comprobante-e.com' }}
</p>
<p style="font-size: 11.5px; line-height: 1.45; margin-bottom: 16px;">
    y luego presionar el botón <strong>"PORTAL PARA CONTADORES"</strong>.
</p>

<p style="font-size: 11px; color: #444; margin-bottom: 18px; line-height: 1.4;">
    La plataforma contiene el detalle de los comprobantes electrónicos de venta emitidos por la empresa: <br>
    <strong>{{ $clienteRuc }} - {{ $clienteRazonSocial }}</strong>
</p>

<div style="color: #eb5454; font-weight: bold; font-size: 12px; margin-top: 15px; margin-bottom: 4px; text-transform: uppercase;">
    CONFIGURACIÓN DE SERIES
</div>

@php
    $series = $facturacion['series'] ?? [
        ['tipo' => 'Serie factura', 'serie' => 'F040'],
        ['tipo' => 'Serie boleta', 'serie' => 'B040'],
        ['tipo' => 'Serie Nota de Crédito', 'serie' => 'NC40'],
    ];
@endphp

<table class="cred-table cred-table-left">
    <tbody>
        @foreach($series as $s)
            <tr>
                <td style="background-color: #eb5454; color: #fff; font-weight: bold; width: 45%; text-align: center;">
                    {{ $s['tipo'] }}
                </td>
                <td class="val" style="width: 55%; font-size: 12px;">
                    {{ $s['serie'] }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="color: #eb5454; font-weight: bold; font-size: 12px; margin-top: 25px; margin-bottom: 4px; text-transform: uppercase;">
    CREDENCIALES DE ACCESO
</div>

@php
    $credencialesContador = $facturacion['credenciales_contador'] ?? [
        ['usuario' => $clienteRuc, 'clave' => 'clave123']
    ];
@endphp

<table class="cred-table">
    <thead>
        <tr>
            <th style="width: 50%;">Usuario</th>
            <th style="width: 50%;">Clave</th>
        </tr>
    </thead>
    <tbody>
        @foreach($credencialesContador as $cc)
            <tr>
                <td style="font-weight: 600;">{{ $cc['usuario'] }}</td>
                <td style="font-weight: 600;">{{ $cc['clave'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <div class="footer-left">Un producto de Mr. Soft</div>
    <div class="footer-right">4</div>
</div>
<div class="page-break"></div>

<!-- PÁGINAS 6, 7, 8: TUTORIALES DE YOUTUBE -->
@php
    $listaTutoriales = $tutoriales['videos'] ?? [];
    if (empty($listaTutoriales)) {
        $listaTutoriales = [
            ['titulo' => 'Presentación 🍳', 'url' => 'https://youtu.be/us7pS1mjCZE?si=U_e281AnAOFgZ6q3'],
            ['titulo' => 'Recorrido por la plataforma 🍳', 'url' => 'https://youtu.be/q5zDJpZK85g?si=C4P5cWvuECFXdo5p'],
            ['titulo' => '¿Cómo ingresar a la plataforma? 🔐', 'url' => 'https://youtu.be/lL32cakXcus?si=PHxN68vLuE3x7Orp'],
            ['titulo' => '¿Cómo registrar un pedido en salón? 🍽️', 'url' => 'https://youtu.be/Wj5bpyReOD8?si=ngv2NGhu7LBMmb27'],
            ['titulo' => '¿Cómo registrar una venta rápida? ☕', 'url' => 'https://youtu.be/VpWpitK87oo?si=4T-BrRv5b7RYb_lB'],
            ['titulo' => '¿Cómo cobrar una mesa? 💰', 'url' => 'https://youtu.be/t5yrv0Q4f1E?si=DLqBcmI2RSmsTE5T'],
            ['titulo' => '¿Cómo emitir un comprobante de venta electrónico para SUNAT?', 'url' => 'https://youtu.be/oxpqPuOw8Sc?si=qR5oqEwde6P0_qMo'],
            ['titulo' => '¿Cómo disminuir productos comandados? ⬇️', 'url' => 'https://youtu.be/CIxr6MPPqoQ?si=vf8ZOxTUKY1NVyWH'],
            ['titulo' => '¿Cómo anular un producto registrado? 🚫', 'url' => 'https://youtu.be/PmF7jkleJdk?si=iZzipLbjgoi3feyT'],
            ['titulo' => '¿Cómo anular un pedido completo? 🚫', 'url' => 'https://youtu.be/Vb8j6sVXH5Q?si=FlM78NRtEwbhAuiH'],
            ['titulo' => '¿Cómo anular una venta? 🚫', 'url' => 'https://youtu.be/FD2gI9z7qXk?si=PeN7r-4tdHW_flnV'],
            ['titulo' => '¿Cómo cambiar mi contraseña? 🔑', 'url' => 'https://youtu.be/tpvKMZCnBJU?si=ExWy3dp12PR3RrfP'],
            ['titulo' => '¿Cómo crear una nueva categoría de productos? 🍕', 'url' => 'https://youtu.be/SSn6IofCquI?si=wV5WsuLmauEDpGEm'],
            ['titulo' => '¿Cómo crear un nuevo producto? 🍔', 'url' => 'https://youtu.be/WguSM1eJ62o?si=KBgl_GVv2o_RDE02'],
            ['titulo' => '¿Cómo registrar mis gastos? 💸', 'url' => 'https://youtu.be/vV_rctLu4gs?si=09wlGN8Hy-7mKbVH'],
            ['titulo' => '¿Cómo configurar mis productos favoritos? ⭐', 'url' => 'https://youtu.be/cjzyNOTF11M?si=QRPyi5iL7xJi4Ndb'],
            ['titulo' => '¿Cómo controlar mi inventario? 📦', 'url' => 'https://youtu.be/PODRHCv0iis?si=Nd3cwxW1cDf0sExB'],
            ['titulo' => '¿Cómo crear ingredientes? 🥦', 'url' => 'https://youtu.be/63yQtPY1g8U?si=tZaYkX9E_Zef9L5p'],
            ['titulo' => '¿Cómo crear productos compuestos? 🍲', 'url' => 'https://youtu.be/w0y2YNaiL8Y?si=PeZMC-hNZ23JJ_kD'],
            ['titulo' => '¿Cómo configurar tus recetas? 🍳', 'url' => 'https://youtu.be/3Uvo7p23LYw?si=WBdvuuxqv1nhC1yy'],
            ['titulo' => '¿Cómo hacer entradas/salidas de stock de productos? 🚚', 'url' => 'https://youtu.be/Z3bksX0WrEQ?si=i_SoeGvpqxMmQsWl'],
            ['titulo' => '¿Cómo ver mi stock de productos? 📄', 'url' => 'https://youtu.be/2J_U0EFy_as?si=XyT-NXrQy_bDNdjL'],
            ['titulo' => '¿Cómo ver el kárdex de inventario? 📄', 'url' => 'https://youtu.be/XWo2kdtXhTY?si=wXFc-tOy2mWENXa8'],
            ['titulo' => '¿Cómo aperturar caja? 💰', 'url' => 'https://youtu.be/SD-8vguX89M?si=S2-PMcHO-WSonuFp'],
            ['titulo' => '¿Cómo cerrar caja? 💰', 'url' => 'https://youtu.be/U3CI98ky6J0?si=_t8lqqHNvXhUqTdA'],
            ['titulo' => '¿Cómo registrar un pedido de PedidosYa o Rappi? 📲', 'url' => 'https://youtu.be/9MydaU3mDTU?si=o6R3KNEdMEACw78h'],
            ['titulo' => '¿Cómo mover una mesa? 🔄', 'url' => 'https://youtu.be/FRe96ByPZxM?si=MXSUOl1VE0yVWOdM'],
            ['titulo' => '¿Cómo cambiar el nombre de un producto para mi comprobante de venta electrónico? ✏️', 'url' => 'https://youtu.be/zDpZ4-uWMJc?si=jOkHB--8OGUn7qjr'],
            ['titulo' => '¿Cómo aplicar descuento a un producto? 🏷️', 'url' => 'https://youtu.be/U5eX_8jTDgY?si=H7U9yRSBJCypStVo'],
            ['titulo' => '¿Cómo aplicar un descuento a todo mi pedido? 🏷️', 'url' => 'https://youtu.be/llZV8dp1syA?si=73bM1QqpQjWpm9UV'],
            ['titulo' => '¿Cómo dar una cortesía completa? 🎁', 'url' => 'https://youtu.be/AXgsL2WLEIs?si=6AgM33O5DLKlWu6Q'],
            ['titulo' => '¿Cómo dividir cuenta por productos? ✂️', 'url' => 'https://youtu.be/lCa6ip__usc?si=HE5KfVXP9r6mocIz'],
            ['titulo' => '¿Cómo dividir cuenta por montos? ✂️', 'url' => 'https://youtu.be/H8Yp0EQCuro?si=eOuDEQTPNOMQaMGX'],
            ['titulo' => '¿Cómo cambiar el medio de pago de una venta? 💵', 'url' => 'https://youtu.be/wIVYEN2lG3E?si=rwe-AqCN0Yu2WRGX'],
            ['titulo' => '¿Cómo hacer un comprobante de venta electrónico por consumo?', 'url' => 'https://youtu.be/U-kLc65qoKg?si=NuUGff4cn1_Rpcvu'],
            ['titulo' => '¿Cómo hacer un comprobante de venta electrónico por glosa?', 'url' => 'https://youtu.be/2Np51QFi7pE?si=wcbMZCpGvygSMplQ'],
            ['titulo' => '¿Cómo enviar un comprobante por correo o WhatsApp? 📩', 'url' => 'https://youtu.be/LIwf62k48XU?si=vHt2RBnI10JAVJNK'],
            ['titulo' => '¿Cómo hacer una venta al crédito? 💳', 'url' => 'https://youtu.be/jxRReJbF7f8?si=0Z8EF1g9GfgYrj9Q'],
            ['titulo' => '¿Cómo pagar una venta al crédito? 💵', 'url' => 'https://youtu.be/fwKCn4O_Jjg?si=-5x-opgNTzdLk1Jo'],
        ];
    }

    $chunks = array_chunk($listaTutoriales, 13);
    $currentPageNum = 5;
@endphp

@foreach($chunks as $chunkIndex => $chunkVideos)
    <div class="header-logo">
        <span class="header-logo-text">{{ $producto->nombre }}</span><br>
        <span class="header-logo-sub">{{ $slogan }}</span>
    </div>

    @if($chunkIndex === 0)
        <div class="section-title">{{ $tutoriales['titulo'] ?? "TUTORIALES PARA USO DE $nombreProducto" }}</div>

        <p style="font-size: 11.5px; line-height: 1.4; margin-bottom: 8px;">
            En la plataforma YouTube en el canal oficial de <strong>Mr. Soft</strong> encontrarás vídeos que explican las pantallas y la funcionalidad de nuestra plataforma <strong>{{ $producto->nombre }}</strong>.
        </p>
        <p style="font-size: 11.5px; line-height: 1.4; margin-bottom: 12px;">
            De esta manera te ayudamos a lograr un mejor aprovechamiento de nuestra plataforma:
        </p>

        <table class="channel-info-box">
            <tr>
                <th>Plataforma</th>
                <td>{{ $tutoriales['plataforma'] ?? 'YouTube' }}</td>
            </tr>
            <tr>
                <th>Canal</th>
                <td>{{ $tutoriales['canal'] ?? 'Mr Soft' }}</td>
            </tr>
            <tr>
                <th>Nombre</th>
                <td>{{ $tutoriales['nombre_playlist'] ?? "$nombreProducto - Software de gestión" }}</td>
            </tr>
            <tr>
                <th>Enlace</th>
                <td><a href="{{ $tutoriales['enlace_playlist'] ?? '#' }}" style="color: #eb5454; word-break: break-all;">{{ $tutoriales['enlace_playlist'] ?? 'https://www.youtube.com/' }}</a></td>
            </tr>
        </table>
    @endif

    <table class="tut-table">
        <thead>
            <tr>
                <th style="width: 45%;">Tutorial</th>
                <th style="width: 55%;">Enlace</th>
            </tr>
        </thead>
        <tbody>
            @foreach($chunkVideos as $video)
                <tr>
                    <td style="font-weight: 500;">{{ $video['titulo'] }}</td>
                    <td>
                        <a href="{{ $video['url'] }}" class="tut-link" target="_blank">{{ $video['url'] }}</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">Un producto de Mr. Soft</div>
        <div class="footer-right">{{ $currentPageNum + $chunkIndex }}</div>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
@endif

</body>
</html>
