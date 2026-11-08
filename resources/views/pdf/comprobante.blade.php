<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $numeroComprobante }}</title>
    <style>
        @page { margin: 22px 42px 28px; }
        * { box-sizing: border-box; }
        body { color: #111; font-family: Arial, DejaVu Sans, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; }
        .header { width: 100%; height: 132px; }
        .issuer { width: 65%; vertical-align: top; padding: 68px 8px 0 8px; }
        .issuer-name { font-size: 18px; font-weight: bold; margin-bottom: 7px; }
        .issuer-address { font-size: 13px; line-height: 1.45; }
        .document-box { width: 35%; height: 105px; border: 1px solid #111; text-align: center; vertical-align: middle; padding: 10px; }
        .document-title { font-size: 22px; line-height: 1.1; font-weight: bold; margin: 0 0 9px; text-transform: uppercase; }
        .document-ruc, .document-number { font-size: 16px; line-height: 1.5; }
        .buyer-box { width: 100%; border: 1px solid #111; margin: 0 0 14px; padding: 8px 13px; }
        .buyer-box td { font-size: 11px; line-height: 1.4; vertical-align: top; }
        .buyer-label { width: 22%; font-weight: bold; white-space: nowrap; padding-right: 10px; }
        .buyer-value { width: 78%; }
        .items { width: 100%; table-layout: fixed; margin-top: 18px; }
        .items th, .items td { border: 1px solid #111; padding: 5px 4px; font-size: 9px; }
        .items th { text-align: center; font-weight: bold; height: 23px; }
        .items td { vertical-align: middle; }
        .items .item { width: 5%; text-align: center; }
        .items .description { width: 40%; text-align: left; line-height: 1.35; }
        .items .unit { width: 6%; text-align: center; }
        .items .quantity { width: 8%; text-align: right; }
        .items .unit-value { width: 8%; text-align: right; }
        .items .price { width: 8%; text-align: right; }
        .items .discount { width: 8%; text-align: right; }
        .items .sale-value { width: 17%; text-align: right; }
        .summary { position: relative; height: 190px; margin-top: 36px; width: 100%; }
        .amount-words { position: absolute; top: 14px; left: 2px; width: 58%; font-size: 11px; text-transform: uppercase; }
        .totals { position: absolute; top: 0; right: 22%; width: 18%; border-collapse: collapse; }
        .totals td { font-size: 11px; line-height: 1.45; padding: 2px 4px; }
        .totals .label { font-weight: bold; width: 56%; }
        .totals .value { width: 44%; text-align: right; white-space: nowrap; }
        .totals .total td { border-top: 1px solid #555; font-size: 11px; font-weight: bold; }
        .footer { margin-top: 0; font-size: 9px; line-height: 1.5; }
        .footer-number { margin-top: 16px; font-size: 10px; }
    </style>
</head>
<body>
@php
    $detalles = $comprobante->detalles ?? collect();
    $gravada = $detalles->where('tipo_igv', '10')->sum('subtotal');
    $exonerada = $detalles->where('tipo_igv', '20')->sum('total');
    $inafecta = $detalles->where('tipo_igv', '30')->sum('total');
@endphp
<main>
    <table class="header">
        <tr>
            <td class="issuer">
                <div class="issuer-name">{{ $facturador?->razon_social ?: ($facturador?->nombre_comercial ?: 'GARZASOFT E.I.R.L.') }}</div>
                <div class="issuer-address">{{ $facturador?->direccion ?: '-' }}</div>
            </td>
            <td class="document-box">
                <div class="document-title">{{ $tipoDocumento }}</div>
                <div class="document-ruc">RUC: {{ $facturador?->ruc ?: '-' }}</div>
                <div class="document-number">{{ $numeroComprobante }}</div>
            </td>
        </tr>
    </table>

    <table class="buyer-box">
        <tr><td class="buyer-label">Fecha Emisión:</td><td class="buyer-value">{{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</td></tr>
        <tr><td class="buyer-label">RUC / DNI:</td><td class="buyer-value">{{ $cliente?->ruc ?: $comprobante->receptor_documento ?: $contacto?->dni ?: '-' }}</td></tr>
        <tr><td class="buyer-label">Señor(es):</td><td class="buyer-value">{{ $cliente?->razon_social ?: $cliente?->nombre_comercial ?: $comprobante->receptor_nombre ?: $contacto?->nombre ?: '-' }}</td></tr>
        <tr><td class="buyer-label">Dirección:</td><td class="buyer-value">{{ $cliente?->direccion ?: $comprobante->receptor_direccion ?: '-' }}</td></tr>
        <tr><td class="buyer-label">Moneda:</td><td class="buyer-value">{{ strtoupper((string) $comprobante->moneda) }}</td></tr>
        <tr><td class="buyer-label">Forma de Pago:</td><td class="buyer-value">{{ $formaPago }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="item">Item</th>
                <th class="description">Descripción</th>
                <th class="unit">UM</th>
                <th class="quantity">Cantidad</th>
                <th class="unit-value">V.U.</th>
                <th class="price">P.U.</th>
                <th class="discount">Dscto.</th>
                <th class="sale-value">Valor Venta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detalles as $index => $detalle)
                @php
                    $cantidad = (float) $detalle->cantidad;
                    $valorUnitario = $cantidad > 0 ? (float) $detalle->subtotal / $cantidad : 0;
                @endphp
                <tr>
                    <td class="item">{{ $index + 1 }}</td>
                    <td class="description">{{ $detalle->descripcion }}</td>
                    <td class="unit">{{ $detalle->unidad ?: 'NIU' }}</td>
                    <td class="quantity">{{ number_format($cantidad, 2) }}</td>
                    <td class="unit-value">{{ number_format($valorUnitario, 2) }}</td>
                    <td class="price">{{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                    <td class="discount">0.00</td>
                    <td class="sale-value">{{ number_format((float) $detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="amount-words">SON: {{ $importeEnLetras }}</div>
        <table class="totals">
            <tr><td class="label">Op. Gravada:</td><td class="value">{{ number_format((float) $gravada, 2) }}</td></tr>
            <tr><td class="label">I.G.V.:</td><td class="value">{{ number_format((float) $comprobante->igv, 2) }}</td></tr>
            <tr><td class="label">Op. Inafecta:</td><td class="value">{{ number_format((float) $inafecta, 2) }}</td></tr>
            <tr><td class="label">Op. Exonerada:</td><td class="value">{{ number_format((float) $exonerada, 2) }}</td></tr>
            <tr class="total"><td class="label">Importe Total</td><td class="value">{{ number_format((float) $comprobante->total, 2) }}</td></tr>
        </table>
    </div>

    <div class="footer">
        <div class="footer-note">Representación impresa de la {{ strtolower($tipoDocumento) }}, consulte en https://facturae-garzasoft.com</div>
        <div class="footer-number">{{ $numeroComprobante }}</div>
    </div>
</main>
</body>
</html>
