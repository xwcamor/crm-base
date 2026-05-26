@php
    $fmtMoney = fn ($n) => number_format((float) ($n ?? 0), 2, '.', ',');
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; margin: 0; padding: 0; line-height: 1.4; }
        .wrap { padding: 24px 28px; }
        .top { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1677ff; padding-bottom: 10px; }
        .top-left, .top-right { display: table-cell; vertical-align: bottom; }
        .top-right { text-align: right; }
        .tenant-name { font-size: 14px; font-weight: 700; color: #1f1f1f; }
        .doc-title { font-size: 18px; font-weight: 700; color: #1677ff; }
        .subtitle { font-size: 10px; color: #8c8c8c; margin-top: 2px; }

        .filters-summary { background: #fafafa; padding: 8px 12px; border-radius: 4px; margin-bottom: 14px; font-size: 10px; color: #595959; }
        .filters-summary strong { color: #262626; }

        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section h3 { margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #1f1f1f; padding-bottom: 4px; border-bottom: 1px solid #e8e8e8; }

        table.data { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.data th {
            background: #fafafa; color: #595959;
            text-transform: uppercase; letter-spacing: 0.4px;
            padding: 6px 8px; text-align: left; border-bottom: 1px solid #d9d9d9;
            font-size: 9px;
        }
        table.data th.r, table.data td.r { text-align: right; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        table.data tr:last-child td { border-bottom: 0; }
        table.data tr.totals td { font-weight: 700; border-top: 2px solid #262626; }

        .small-muted { font-size: 9px; color: #bfbfbf; text-align: center; margin-top: 14px; }
        .empty { padding: 12px; color: #8c8c8c; font-style: italic; font-size: 10px; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div class="top-left">
            <div class="tenant-name">{{ $tenant->name ?? config('app.name') }}</div>
            <div class="subtitle">Generado el {{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div class="top-right">
            <div class="doc-title">{{ $title }}</div>
        </div>
    </div>

    @if (!empty($filtersSummary))
        <div class="filters-summary">
            <strong>Filtros aplicados:</strong> {{ $filtersSummary }}
        </div>
    @endif

    @foreach ($sections as $section)
        <div class="section">
            <h3>{{ $section['title'] }}</h3>
            @if (empty($section['rows']))
                <div class="empty">Sin datos para este filtro.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            @foreach ($section['columns'] as $col)
                                <th class="{{ $col['align'] === 'right' ? 'r' : '' }}">{{ $col['title'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                @foreach ($section['columns'] as $col)
                                    @php
                                        $key = $col['key'];
                                        $val = is_array($row) ? ($row[$key] ?? null) : ($row->$key ?? null);
                                        if (($col['type'] ?? null) === 'money' && $val !== null) {
                                            $val = ($col['currency'] ?? '') . ' ' . $fmtMoney($val);
                                        } elseif (($col['type'] ?? null) === 'date' && $val !== null) {
                                            $val = $fmtDate($val);
                                        } elseif (($col['type'] ?? null) === 'pct' && $val !== null) {
                                            $val = $val . '%';
                                        } elseif ($val === null) {
                                            $val = '—';
                                        }
                                    @endphp
                                    <td class="{{ $col['align'] === 'right' ? 'r' : '' }}">{{ $val }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="small-muted">{{ $tenant->name ?? config('app.name') }} · {{ now()->format('d/m/Y H:i') }}</div>
</div>
</body>
</html>
