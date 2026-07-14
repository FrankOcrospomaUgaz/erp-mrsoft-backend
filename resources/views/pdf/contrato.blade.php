<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $contrato->numero }}</title>
    <style>
        @page { margin: 26px 54px; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 12px; line-height: 1.12; }
        p { margin: 0 0 4px 0; text-align: justify; }
        .title { font-weight: 700; text-transform: uppercase; text-decoration: underline; text-align: center; margin: 1px 0; }
        .title-number { font-weight: 700; text-align: center; margin-top: 56px; margin-bottom: 2px; }
        .contract-body { margin-top: 10px; }
        .clause { margin-top: 10px; }
        .clause-title { font-weight: 700; text-transform: uppercase; text-decoration: underline; }
        .detail-table { width: 100%; border-collapse: collapse; margin: 10px 0 8px; font-size: 11px; }
        .detail-table th, .detail-table td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
        .detail-table th { text-align: center; font-weight: 700; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        ul { margin: 2px 0 0 18px; padding-left: 14px; }
        li { margin-bottom: 2px; }
        .bank-block { margin-top: 8px; white-space: pre-line; }
        .signature-space { height: 44px; }
        .signature-table { width: 100%; margin-top: 40px; border-collapse: collapse; }
        .signature-table td { width: 50%; text-align: center; border: none; padding: 0 18px; vertical-align: bottom; }
        .signature-line {
            border-top: 1px solid #000;
            padding-top: 8px;
            width: 85%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    @php
        $brandMap = [
            'hotelhub' => 'HotelHUB',
            'gesrest' => 'Gesrest',
            '360sys' => '360Sys',
        ];
        $empresaCliente = $jerarquia['empresa'] ?? $jerarquia['root'];
        $nombreEmisor = strtoupper($facturador->nombre_comercial ?? $facturador->razon_social ?? 'GARZASOFT EIRL');
        $rucEmisor = $facturador->ruc ?? '20602871119';
        $direccionEmisor = $facturador->direccion ?? 'Calle Nicolas la Torre 126 Urb. Magisterial, Chiclayo, Lambayeque';
        $representanteEmisor = 'AMPUERO PASCO GILBERTO MARTIN';
        $dniRepresentanteEmisor = '16734323';
        $nombreCliente = strtoupper($empresaCliente->razon_social ?? $empresaCliente->nombre_comercial ?? 'CLIENTE');
        $rucCliente = $empresaCliente->ruc ?? 'N/D';
        $representanteCliente = strtoupper($cliente->dueno_nombre ?? $empresaCliente->dueno_nombre ?? 'SIN REPRESENTANTE');
        $dniCliente = $cliente->contactos_clientes[0]->dni ?? 'N/D';
        $productoPrincipalRaw = $modulosAgrupados->first()['producto'] ?? strtoupper($tipoContratoDescripcion);
        $productoPrincipal = $brandMap[strtolower($productoPrincipalRaw)] ?? $productoPrincipalRaw;
        $periodicidadPago = $contrato->periodicidad_cuota === 'anual' ? 'anual' : 'mensual';
        $descripcionServicio = 'Pago ' . strtoupper($periodicidadPago === 'anual' ? 'ANUAL' : 'MENSUAL') . ' por servicio de plataforma de software para alojamiento ' . $productoPrincipal;
        $baseServicio = collect($contrato->contratoProductoModulos)->sum('precio');
        $cuotas = $contrato->cuotas->sortBy('fecha_vencimiento')->values();
        $fechaInicioContrato = \Carbon\Carbon::parse($contrato->fecha_inicio);
        $fechaFinContrato = \Carbon\Carbon::parse($contrato->fecha_fin);
        $fechaContrato = $fechaInicioContrato->copy()->subDay();
        $mesesCantidad = $contrato->vigencia_contrato === 'anual'
            ? ((int) ($contrato->duracion_anios ?: 1) * 12)
            : 6;
        $mesesTexto = match ($mesesCantidad) {
            6 => 'seis (6)',
            12 => 'doce (12)',
            24 => 'veinticuatro (24)',
            36 => 'treinta y seis (36)',
            48 => 'cuarenta y ocho (48)',
            60 => 'sesenta (60)',
            default => $mesesCantidad . ' (' . $mesesCantidad . ')',
        };
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $fechaInicioTexto = $fechaInicioContrato->format('d') . ' de ' . ucfirst($meses[(int) $fechaInicioContrato->format('n')]) . ' del ' . $fechaInicioContrato->format('Y');
        $fechaFinTexto = $fechaFinContrato->format('d') . ' de ' . ucfirst($meses[(int) $fechaFinContrato->format('n')]) . ' del ' . $fechaFinContrato->format('Y');
        $fechaFirmaMes = $meses[(int) $fechaContrato->format('n')];
        $cuotasTexto = match ($cuotas->count()) {
            1 => 'una',
            2 => 'dos',
            3 => 'tres',
            4 => 'cuatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'siete',
            8 => 'ocho',
            9 => 'nueve',
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            default => (string) $cuotas->count(),
        };
    @endphp

    <div class="title-number">CONTRATO N&deg; {{ $contrato->numero }}</div>
    <div class="title">CONTRATO DEL SERVICIO DE ARRENDAMIENTO DE LA PLATAFORMA DE SOFTWARE PARA</div>
    <div class="title">ALOJAMIENTO {{ strtoupper($productoPrincipal) }}</div>

    <div class="contract-body">
        <p>
            Conste por el presente documento el contrato del servicio de arrendamiento de la plataforma de software
            para alojamiento {{ $productoPrincipal }}, que celebran de una parte <strong>{{ $nombreEmisor }}</strong>
            con RUC N&deg; <strong>{{ $rucEmisor }}</strong>, domicilio en {{ $direccionEmisor }}, y debidamente representada
            por su representante legal quien firma el presente documento, <strong>{{ $representanteEmisor }}</strong> con
            DNI {{ $dniRepresentanteEmisor }}, en adelante <strong>EL ARRENDADOR</strong> y de otra parte
            <strong>{{ $nombreCliente }}</strong> con RUC N&deg; <strong>{{ $rucCliente }}</strong> representada por el senor
            <strong>{{ $representanteCliente }}</strong> con DNI N&deg; <strong>{{ $dniCliente }}</strong>, en adelante
            <strong>EL CLIENTE</strong>, en los terminos y condiciones siguientes:
        </p>

        <div class="clause">
            <div class="clause-title">CLAUSULA PRIMERA: ANTECEDENTES</div>
            <p>
                Con fecha {{ $fechaContrato->format('d-m-Y') }}, <strong>EL ARRENDADOR</strong> envio la cotizacion para
                el Arrendamiento de la plataforma de software para alojamiento {{ $productoPrincipal }} para
                <strong>EL CLIENTE</strong>, cuyos detalles y totales, se detallan a continuacion:
            </p>

            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width: 42px;">Item</th>
                        <th>Descripcion</th>
                        <th style="width: 78px;">P.Unitario</th>
                        <th style="width: 58px;">Cantidad</th>
                        <th style="width: 92px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">01</td>
                        <td>Pago instalacion del servicio de plataforma de software para alojamiento {{ $productoPrincipal }}</td>
                        <td class="text-center">S/ 0.00</td>
                        <td class="text-center">1</td>
                        <td class="text-center">S/ 0.00</td>
                    </tr>
                    <tr>
                        <td class="text-center">02</td>
                        <td>{{ $descripcionServicio }}</td>
                        <td class="text-center">S/ {{ number_format($baseServicio, 2, '.', '') }}</td>
                        <td class="text-center">{{ $periodicidadPago === 'anual' ? max(1, (int) $contrato->duracion_anios) : max(1, $cuotas->count()) }}</td>
                        <td class="text-center">S/ {{ number_format((float) $contrato->total, 2, '.', '') }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-right"><strong>TOTAL DEL CONTRATO CON IGV S/ {{ number_format((float) $contrato->total, 2, '.', '') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA SEGUNDA: OBJETO</div>
            <p>
                El presente proceso contrato tiene por objeto el Arrendamiento de la plataforma de software para
                alojamiento {{ $productoPrincipal }} para <strong>EL CLIENTE</strong>.
            </p>
            <ul>
                @foreach($contrato->contratoProductoModulos as $item)
                    <li>Modulo {{ $item->modulo?->nombre ?? 'Servicio' }}</li>
                @endforeach
            </ul>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA TERCERA: MONTO CONTRACTUAL</div>
            <p>
                El monto total del arrendamiento materia del presente contrato asciende a
                <strong>S/ {{ number_format((float) $contrato->total, 2, '.', '') }} ({{ $montoTotalLetras }})</strong>,
                incluido el IGV,
                @if($contrato->forma_pago === 'parcial')
                    el cual sera cancelado en {{ $cuotasTexto }} ({{ $cuotas->count() }}) cuota{{ $cuotas->count() === 1 ? '' : 's' }}
                    {{ $periodicidadPago }}{{ $cuotas->count() === 1 ? '' : 'es' }}
                    de S/ {{ number_format((float) ($cuotas->first()->monto ?? 0), 2, '.', '') }} soles.
                @else
                    el cual sera cancelado en un solo pago.
                @endif
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA CUARTA: FORMA DE PAGO</div>
            <p>
                <strong>EL CLIENTE</strong> se obliga a pagar la contraprestacion del servicio en modalidad {{ $periodicidadPago }}
                a <strong>EL ARRENDADOR</strong> en moneda Soles luego de la firma del presente contrato y antes de iniciar el uso de la
                plataforma {{ $productoPrincipal }}, mediante deposito en cuenta bancaria de la empresa
                <strong>EL ARRENDADOR</strong>.
            </p>

            <div class="bank-block">Cuentas a nombre de {{ $nombreEmisor }}

BANCO CONTINENTAL DEL PERU (BBVA)
Cuenta en soles
0011-0442-0200095395-16
Cuenta CCI en soles
011-442-000200095395-16

BANCO DE CREDITO DEL PERU (BCP)
Cuenta en soles
415-2646186-0-69
Cuenta CCI en soles
00241500264618606989</div>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA QUINTA: INICIO Y CULMINACION DE LA PRESTACION</div>
            <p>
                La vigencia del presente contrato se extendera a partir del dia siguiente a su suscripcion hasta por un tiempo
                de {{ $mesesTexto }} meses.
            </p>
            <p>
                Quedando definido que el plazo de arrendamiento empezara a computarse desde el dia
                <strong>{{ $fechaInicioTexto }}</strong>
                y culminara el
                <strong>{{ $fechaFinTexto }}</strong>.
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA SEXTA: DOCUMENTOS MATERIA DEL CONTRATO</div>
            <p>
                El presente contrato esta conformado por la cotizacion aceptada por <strong>EL CLIENTE</strong>.
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA SEPTIMA: CONFORMIDAD DE LOS BIENES</div>
            <p>
                La conformidad a la recepcion de la prestacion a cargo de <strong>EL CLIENTE</strong> sera dada por la
                Gerencia General o el Representante Legal.
            </p>
            <p>
                De existir observaciones en la conformidad del servicio materia de este contrato, se consignaran en el acta respectiva,
                indicandose claramente el sentido de estas, dandose a <strong>EL ARRENDADOR</strong> plazo prudencial para su subsanacion,
                en funcion a la complejidad.
            </p>
            <p>
                Si pese al plazo otorgado, <strong>EL ARRENDADOR</strong> no cumpliese a cabalidad con la subsanacion,
                <strong>EL CLIENTE</strong> podra resolver el contrato, sin perjuicio de aplicar las penalidades que correspondan.
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA OCTAVA: RESPONSABILIDADES DE EL ARRENDADOR Y EL CLIENTE</div>
            <p><strong>EL ARRENDADOR</strong> tendra las siguientes responsabilidades:</p>
            <ul>
                <li>Instalar y configurar la plataforma de software para alojamiento {{ $productoPrincipal }} en los equipos que indique EL CLIENTE para los modulos contratados.</li>
                <li>Ofrecer un nivel de atencion de servicio ante fallas, no mayor a cuarenta y ocho (48) horas de reportado el incidente, en horario de lunes a sabado de 09:00 a 18:00 horas.</li>
                <li>Poner a disposicion del cliente la URL https://hotelhub.com.pe para uso de la plataforma de software para alojamiento {{ $productoPrincipal }}.</li>
                <li>Poner a disposicion del cliente la URL https://comprobante-e.com para consulta de sus clientes de los comprobantes electronicos de venta emitidos y consulta del contador en rango de fechas.</li>
                <li>Almacenar en su servidor los datos resultado del uso de la plataforma de software para alojamiento {{ $productoPrincipal }} por el plazo de duracion de este contrato.</li>
                <li>Cuando EL CLIENTE tenga retraso en el pago, EL ARRENDADOR puede suspender el servicio, sin perjuicio de aplicar las penalidades que correspondan.</li>
            </ul>

            <p><strong>EL CLIENTE</strong> tendra las siguientes responsabilidades:</p>
            <ul>
                <li>Contar con una conexion a Internet adecuada que asegure la correcta operacion de la plataforma de software para alojamiento {{ $productoPrincipal }}.</li>
                <li>Informar con un plazo no mayor a veinticuatro (24) horas sobre incidencias en el funcionamiento de la plataforma que impidan su correcto funcionamiento.</li>
                <li>Garantizar y custodiar el correcto funcionamiento de los equipos de computo como computadoras e impresoras que garanticen el funcionamiento de la plataforma.</li>
                <li>Realizar el pago por el servicio dentro de los plazos establecidos en la Clausula Tercera de este contrato.</li>
            </ul>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA NOVENA: RESOLUCION DEL CONTRATO</div>
            <p>Constituiran causales de resolucion del presente contrato las siguientes:</p>
            <p>1. El acuerdo mutuo de ambas partes.</p>
            <p>2. Cuando EL CLIENTE tenga retraso en el pago en reiteradas oportunidades EL ARRENDADOR puede finalizar el contrato, estando obligado EL CLIENTE al pago integro de los saldos del presente contrato.</p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA DECIMA: DE LA CONFIDENCIALIDAD</div>
            <p>
                <strong>EL ARRENDADOR</strong> guardara confidencialidad sobre la informacion que le facilite
                <strong>EL CLIENTE</strong> en o para la ejecucion del contrato o que por su propia naturaleza deba ser tratada como tal.
                Se excluye de la categoria de informacion confidencial toda aquella informacion que sea divulgada por EL CLIENTE,
                aquella que haya de ser revelada de acuerdo con las leyes o con una resolucion judicial o acto de autoridad competente.
                Este deber se mantendra aun con posterioridad a la finalizacion del servicio.
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA DECIMO PRIMERA: MARCO LEGAL DEL CONTRATO</div>
            <p>
                Solo en lo no previsto en este contrato y demas normativa especial que resulte aplicable, se utilizaran las disposiciones
                pertinentes del Codigo Civil vigente y demas normas concordantes.
            </p>
        </div>

        <div class="clause">
            <div class="clause-title">CLAUSULA DECIMO SEGUNDA: SOLUCION DE CONTROVERSIAS</div>
            <p>
                Todos los conflictos que se deriven de la ejecucion e interpretacion del presente contrato, incluidos los que se refieran
                a su nulidad e invalidez, seran resueltos de manera definitiva e inapelable mediante arbitraje de derecho.
            </p>
            <p>
                Facultativamente, cualquiera de las partes podra someter a conciliacion la referida controversia, sin perjuicio de recurrir
                al arbitraje en caso no se llegue a un acuerdo entre ambas.
            </p>
            <p>
                De acuerdo con la cotizacion, las partes lo firman por duplicado en senal de conformidad en la ciudad de Chiclayo a los
                {{ $fechaContrato->format('d') }} dias del mes de {{ $fechaFirmaMes }} del {{ $fechaContrato->format('Y') }}.
            </p>
        </div>

        <div class="signature-space"></div>
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line">
                        <strong>EL ARRENDADOR</strong>
                    </div>
                </td>
                <td>
                    <div class="signature-line">
                        <strong>EL CLIENTE</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
