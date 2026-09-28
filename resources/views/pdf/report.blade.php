<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 72px 24px 40px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #24364b; font-size: 8px; margin: 0; }
        header { position: fixed; top: -55px; left: 0; right: 0; height: 48px; border-bottom: 3px solid #167ca6; }
        .brand { color: #173b63; font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .brand span { color: #167ca6; }
        .header-meta { float: right; text-align: right; color: #66788a; font-size: 7px; line-height: 1.5; }
        footer { position: fixed; bottom: -27px; left: 0; right: 0; border-top: 1px solid #d4dee8; padding-top: 7px; color: #718096; font-size: 7px; }
        .page-number:after { content: counter(page); }
        h1 { font-size: 18px; color: #173b63; margin: 0 0 4px; }
        .subtitle { color: #61758a; margin-bottom: 10px; }
        .filters { background: #edf5f9; border-left: 4px solid #167ca6; padding: 7px 10px; margin-bottom: 10px; color: #40566d; }
        .summary { width: 100%; margin-bottom: 12px; border-spacing: 6px 0; margin-left: -6px; }
        .summary td { background: #173b63; color: white; padding: 8px 10px; border-radius: 3px; }
        .summary .label { text-transform: uppercase; opacity: .75; font-size: 6px; letter-spacing: .5px; }
        .summary .value { font-size: 12px; font-weight: bold; margin-top: 3px; }
        table.data { width: 100%; border-collapse: collapse; table-layout: auto; }
        table.data th { background: #167ca6; color: white; padding: 6px 4px; text-align: left; font-size: 7px; text-transform: uppercase; }
        table.data td { padding: 5px 4px; border-bottom: 1px solid #dce5ed; vertical-align: top; word-break: break-word; }
        table.data tr:nth-child(even) td { background: #f4f8fb; }
        .number { text-align: right; white-space: nowrap; }
        .empty { text-align: center; padding: 25px !important; color: #718096; }
    </style>
</head>
<body>
<header>
    <span class="brand">MR<span>SOFT</span> <small style="font-size:8px;color:#718096">ERP</small></span>
    <div class="header-meta">REPORTE GERENCIAL<br>{{ $report['generated_at'] }}</div>
</header>
<footer>
    Documento generado por MRSoft ERP
    <span style="float:right">Página <span class="page-number"></span></span>
</footer>

<h1>{{ $report['title'] }}</h1>
<div class="subtitle">{{ $report['description'] }}</div>
<div class="filters">
    <strong>Filtros aplicados:</strong>
    @forelse($filters as $key => $value)
        @if($value !== null && $value !== '' && $value !== false)
            {{ str($key)->replace('_', ' ')->title() }}: {{ $value === true ? 'Sí' : $value }}{{ !$loop->last ? ' · ' : '' }}
        @endif
    @empty
        Todos los registros
    @endforelse
</div>

<table class="summary"><tr>
    @foreach($report['summary'] as $key => $value)
        <td>
            <div class="label">{{ str($key)->replace('_', ' ')->title() }}</div>
            <div class="value">{{ in_array($key, ['total', 'deuda', 'pagado']) ? 'S/ '.number_format($value, 2) : $value }}</div>
        </td>
    @endforeach
</tr></table>

<table class="data">
    <thead><tr>
        @foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach
    </tr></thead>
    <tbody>
        @forelse($report['rows'] as $row)
            <tr>
                @foreach($row as $index => $cell)
                    <td class="{{ in_array($index, $report['currencyColumns']) ? 'number' : '' }}">
                        {{ in_array($index, $report['currencyColumns']) ? 'S/ '.number_format((float)$cell, 2) : ($cell ?? '—') }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td class="empty" colspan="{{ count($report['columns']) }}">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
