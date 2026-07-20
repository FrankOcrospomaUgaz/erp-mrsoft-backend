<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $numeroComprobante }}</title>
    <link rel="stylesheet" href="{{ public_path('css/pdf.css') }}">
</head>
<body>
    <main>
        <table align="left" width="100%">
            <tr>
                <td width="65%" valign="top">
                    <p class="without-bottom">
                        <span class="large bold">{{ $facturador?->nombre_comercial ?: 'MrSoft' }}</span><br>
                        <span class="small bold">{{ $facturador?->razon_social ?: 'Empresa emisora' }}</span><br>
                        <span class="x-small">
                            {{ $facturador?->direccion ?: '-' }}<br>
                            @if(!empty($facturador?->empresa_id))
                                Empresa ID: {{ $facturador->empresa_id }}<br>
                            @endif
                        </span>
                    </p>
                </td>
                <td class="table-soft-bordered" align="center" width="35%" valign="top">
                    <h2 class="without-tb primary-text">{{ $tipoDocumento }}</h2>
                    <p class="medium without-tb">
                        RUC: {{ $facturador?->ruc ?: '-' }}<br>
                        {{ $numeroComprobante }}<br>
                    </p>
                </td>
            </tr>
        </table>

        <table width="100%" class="small">
            <tr>
                <td class="bold" width="30%">Fecha de emision:</td>
                <td width="70%">{{ optional($comprobante->fecha_emision)->format('d/m/Y') }} {{ $comprobante->hora_emision }}</td>
            </tr>
            <tr>
                <td class="bold">Senor(es):</td>
                <td>{{ $cliente?->razon_social ?: $cliente?->nombre_comercial ?: $contacto?->nombre ?: '-' }}</td>
            </tr>
            <tr>
                <td class="bold">RUC/DNI:</td>
                <td>{{ $cliente?->ruc ?: $contacto?->dni ?: '-' }}</td>
            </tr>
            <tr>
                <td class="bold">Direccion:</td>
                <td>{{ $cliente?->direccion ?: '-' }}</td>
            </tr>
            <tr>
                <td class="bold">Moneda:</td>
                <td>{{ strtoupper((string) $comprobante->moneda) }}</td>
            </tr>
            <tr>
                <td class="bold">Forma de pago:</td>
                <td>{{ $formaPago }}</td>
            </tr>
        </table>

        <br>

        <table class="table-full-soft-bordered xx-small" width="100%">
            <thead class="bg-primary white-text">
                <tr>
                    <td align="center">Item</td>
                    <td align="center">Descripcion</td>
                    <td align="center">U.M.</td>
                    <td align="center">Cantidad</td>
                    <td align="center">V.U.</td>
                    <td align="center">IGV</td>
                    <td align="center">Valor de venta</td>
                </tr>
            </thead>
            <tbody>
                @foreach ($comprobante->detalles as $index => $detalle)
                    <tr>
                        <td align="center">{{ $index + 1 }}</td>
                        <td align="left">{{ $detalle->descripcion }}</td>
                        <td align="center">{{ $detalle->unidad ?: 'NIU' }}</td>
                        <td align="right">{{ number_format((float) $detalle->cantidad, 2) }}</td>
                        <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                        <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $detalle->igv, 2) }}</td>
                        <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $detalle->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <br>

        <table class="xx-small" width="35%" align="right">
            <tr>
                <td class="bold">Subtotal:</td>
                <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $comprobante->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="bold">IGV:</td>
                <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $comprobante->igv, 2) }}</td>
            </tr>
            <tr>
                <td class="bold">Total:</td>
                <td align="right">{{ $monedaSimbolo }} {{ number_format((float) $comprobante->total, 2) }}</td>
            </tr>
        </table>
    </main>
</body>
</html>
