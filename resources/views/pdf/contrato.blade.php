<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $contrato->numero }}</title>
    <style>
        @page {
            margin: 32px 36px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 11px;
            line-height: 1.52;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .header-table,
        .summary-table,
        .detail-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .muted {
            color: #4b5563;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 12px 0 14px;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 12px;
            font-weight: 700;
            margin: 14px 0 8px;
            text-transform: uppercase;
        }

        .box {
            border: 1px solid #9ca3af;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .summary-table td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            vertical-align: top;
        }

        .summary-table .label {
            width: 22%;
            font-weight: 700;
            background: #f3f4f6;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
        }

        .detail-table th {
            background: #f3f4f6;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }

        .clause-title {
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 12px;
            margin-bottom: 4px;
        }

        .clause-body {
            text-align: justify;
            margin: 0 0 8px;
        }

        .signature-table td {
            width: 50%;
            padding-top: 48px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #111827;
            display: inline-block;
            width: 82%;
            padding-top: 6px;
        }

        .text-right {
            text-align: right;
        }

        .mb-8 {
            margin-bottom: 8px;
        }

        .mb-12 {
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="brand">{{ $facturador->nombre_comercial ?? 'MrSoft' }}</div>
                    <div class="muted">{{ $facturador->razon_social ?? 'Empresa emisora' }}</div>
                    <div class="muted">RUC: {{ $facturador->ruc ?? '-' }}</div>
                    <div class="muted">{{ $facturador->direccion ?? 'Direccion fiscal no configurada' }}</div>
                </td>
                <td class="text-right">
                    <div><strong>Contrato N.°</strong> {{ $contrato->numero }}</div>
                    <div><strong>Emision:</strong> {{ $fechaEmision->format('d/m/Y') }}</div>
                    <div><strong>Estado:</strong> {{ strtoupper($contrato->estado) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">Contrato de Prestacion de Servicios</div>

    <p class="clause-body">
        Conste por el presente documento el contrato de prestacion de servicios que celebran, de una parte,
        <strong>{{ $facturador->razon_social ?? ($facturador->nombre_comercial ?? 'MrSoft') }}</strong>,
        identificada con RUC <strong>{{ $facturador->ruc ?? '-' }}</strong>, con domicilio en
        <strong>{{ $facturador->direccion ?? 'direccion no configurada' }}</strong>, a quien en adelante se le denominara
        <strong>EL PROVEEDOR</strong>; y, de la otra parte,
        <strong>{{ $jerarquia['root']->razon_social ?: ($jerarquia['root']->nombre_comercial ?? 'EL CLIENTE') }}</strong>
        @if($jerarquia['root']->ruc)
            , con RUC <strong>{{ $jerarquia['root']->ruc }}</strong>
        @endif
        , a quien en adelante se le denominara <strong>EL CLIENTE</strong>, de conformidad con las clausulas siguientes.
    </p>

    <div class="subtitle">1. Datos generales del contrato</div>
    <table class="summary-table mb-12">
        <tr>
            <td class="label">Cliente principal</td>
            <td>{{ $jerarquia['root']->razon_social ?: ($jerarquia['root']->nombre_comercial ?? '-') }}</td>
            <td class="label">RUC cliente</td>
            <td>{{ $jerarquia['root']->ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Empresa</td>
            <td>{{ $jerarquia['empresa']->razon_social ?? ($jerarquia['empresa']->nombre_comercial ?? '-') }}</td>
            <td class="label">Local / sede</td>
            <td>{{ $jerarquia['local']->nombre_comercial ?? ($jerarquia['local']->razon_social ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Direccion del servicio</td>
            <td>{{ $jerarquia['local']->direccion ?? ($cliente->direccion ?? '-') }}</td>
            <td class="label">Tipo de contrato</td>
            <td>{{ ucfirst($tipoContratoDescripcion) }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de inicio</td>
            <td>{{ $fechaInicioTexto }}</td>
            <td class="label">Fecha de finalizacion</td>
            <td>{{ $fechaFinTexto }}</td>
        </tr>
        <tr>
            <td class="label">Vigencia</td>
            <td>{{ ucfirst($vigenciaDescripcion) }}</td>
            <td class="label">Cobranza</td>
            <td>{{ ucfirst($formaPagoDescripcion) }} con periodicidad {{ $periodicidadDescripcion }}</td>
        </tr>
        <tr>
            <td class="label">Monto total</td>
            <td>{{ $montoTotalTexto }}</td>
            <td class="label">Contacto principal</td>
            <td>{{ $cliente->dueno_nombre ?: '-' }} / {{ $cliente->dueno_celular ?: '-' }}</td>
        </tr>
    </table>

    <div class="subtitle">2. Detalle del servicio contratado</div>
    <table class="detail-table mb-12">
        <thead>
            <tr>
                <th style="width: 26px;">#</th>
                <th>Producto / servicio</th>
                <th>Modulo / concepto</th>
                <th style="width: 90px;">Periodicidad</th>
                <th style="width: 92px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @php $indice = 1; @endphp
            @forelse($modulosAgrupados as $grupo)
                @foreach($grupo['items'] as $item)
                    <tr>
                        <td>{{ $indice++ }}</td>
                        <td>{{ $grupo['producto'] }}</td>
                        <td>{{ $item->modulo?->nombre ?? 'Concepto general' }}</td>
                        <td>{{ ucfirst($periodicidadDescripcion) }}</td>
                        <td>{{ 'S/ ' . number_format((float) $item->precio, 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" class="text-right"><strong>Subtotal {{ $grupo['producto'] }}</strong></td>
                    <td><strong>{{ 'S/ ' . number_format((float) $grupo['subtotal'], 2, '.', ',') }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td>1</td>
                    <td>{{ ucfirst($tipoContratoDescripcion) }}</td>
                    <td>Servicio general segun acuerdo comercial</td>
                    <td>{{ ucfirst($periodicidadDescripcion) }}</td>
                    <td>{{ $montoTotalTexto }}</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="4" class="text-right"><strong>Total del contrato</strong></td>
                <td><strong>{{ $montoTotalTexto }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if($contrato->cuotas->isNotEmpty())
        <div class="subtitle">3. Cronograma de pagos</div>
        <table class="detail-table mb-12">
            <thead>
                <tr>
                    <th style="width: 26px;">#</th>
                    <th>Fecha de vencimiento</th>
                    <th>Monto</th>
                    <th>Situacion</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contrato->cuotas as $index => $cuota)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td>{{ 'S/ ' . number_format((float) $cuota->monto, 2, '.', ',') }}</td>
                        <td>{{ ucfirst($cuota->situacion) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="subtitle">4. Clausulas contractuales</div>

    <div class="clause-title">Primera. Objeto</div>
    <p class="clause-body">
        EL PROVEEDOR se obliga a brindar a EL CLIENTE el servicio de {{ $tipoContratoDescripcion }},
        incluyendo los modulos, conceptos y alcances detallados en el presente contrato, para su uso en la sede final
        {{ $jerarquia['local']->nombre_comercial ?? ($cliente->nombre_comercial ?? 'registrada') }}.
    </p>

    <div class="clause-title">Segunda. Vigencia</div>
    <p class="clause-body">
        La vigencia del presente contrato inicia el {{ $fechaInicioTexto }} y culmina el {{ $fechaFinTexto }}.
        La duracion pactada corresponde a {{ $vigenciaDescripcion }}, pudiendo ser renovada por acuerdo escrito entre las partes.
    </p>

    <div class="clause-title">Tercera. Retribucion y forma de pago</div>
    <p class="clause-body">
        EL CLIENTE abonara a favor de EL PROVEEDOR el importe total de {{ $montoTotalTexto }}, bajo la modalidad de
        {{ $formaPagoDescripcion }} y con periodicidad {{ $periodicidadDescripcion }}.
        @if($contrato->cuotas->isNotEmpty())
            El cronograma de cuotas forma parte integrante del presente contrato.
        @endif
    </p>

    <div class="clause-title">Cuarta. Obligaciones del proveedor</div>
    <p class="clause-body">
        EL PROVEEDOR mantendra disponible el servicio contratado, realizara soporte dentro de los alcances comerciales acordados,
        atendera incidencias reportadas por EL CLIENTE y emitira los comprobantes de pago correspondientes de acuerdo con la normativa vigente.
    </p>

    <div class="clause-title">Quinta. Obligaciones del cliente</div>
    <p class="clause-body">
        EL CLIENTE se obliga a usar adecuadamente la plataforma o servicio contratado, cumplir con los pagos en las fechas pactadas,
        designar un contacto responsable para coordinaciones operativas y brindar la informacion necesaria para una correcta implementacion.
    </p>

    <div class="clause-title">Sexta. Confidencialidad</div>
    <p class="clause-body">
        Toda informacion tecnica, comercial, administrativa o de negocio a la que las partes accedan con motivo del presente contrato
        sera tratada como confidencial y no podra ser revelada a terceros sin autorizacion previa y por escrito.
    </p>

    <div class="clause-title">Septima. Propiedad intelectual</div>
    <p class="clause-body">
        Los programas, modulos, desarrollos, mejoras, interfaces, marcas y demas activos vinculados al servicio prestado
        son y seguiran siendo de titularidad de EL PROVEEDOR, salvo pacto escrito distinto.
    </p>

    <div class="clause-title">Octava. Resolucion</div>
    <p class="clause-body">
        El incumplimiento de las obligaciones esenciales asumidas por cualquiera de las partes faculta a la parte afectada
        a resolver el presente contrato, previa comunicacion escrita otorgando un plazo razonable para subsanar el incumplimiento.
    </p>

    <div class="clause-title">Novena. Jurisdiccion</div>
    <p class="clause-body">
        Las partes acuerdan que cualquier controversia derivada de este contrato sera resuelta de forma amigable y, de no ser posible,
        se sometera a la jurisdiccion de los jueces y tribunales del domicilio de EL PROVEEDOR.
    </p>

    <p class="clause-body">
        En senal de conformidad, las partes suscriben el presente contrato en dos ejemplares del mismo tenor.
    </p>

    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line">
                    <strong>EL PROVEEDOR</strong><br>
                    {{ $facturador->razon_social ?? ($facturador->nombre_comercial ?? 'MrSoft') }}<br>
                    RUC: {{ $facturador->ruc ?? '-' }}
                </div>
            </td>
            <td>
                <div class="signature-line">
                    <strong>EL CLIENTE</strong><br>
                    {{ $jerarquia['root']->razon_social ?: ($jerarquia['root']->nombre_comercial ?? '-') }}<br>
                    @if($jerarquia['root']->ruc)
                        RUC: {{ $jerarquia['root']->ruc }}
                    @else
                        Contacto: {{ $cliente->dueno_nombre ?: '-' }}
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
