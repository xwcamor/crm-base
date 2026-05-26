@php
    $fmtMoney = fn ($n) => number_format((float) ($n ?? 0), 2, '.', ',');
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    $cur = $quote->currency_code ?? '';
    $statusLabels = [
        'draft' => 'BORRADOR', 'sent' => 'ENVIADA', 'accepted' => 'ACEPTADA',
        'rejected' => 'RECHAZADA', 'expired' => 'EXPIRADA', 'revised' => 'REVISADA',
    ];
    $statusColor = [
        'accepted' => '#52c41a', 'sent' => '#1677ff', 'rejected' => '#ff4d4f',
        'expired' => '#faad14', 'revised' => '#722ed1', 'draft' => '#8c8c8c',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $quote->reference ?? 'Cotizacion' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; margin: 0; padding: 0; line-height: 1.4; }
        .wrap { padding: 28px 32px; }
        .top { display: table; width: 100%; margin-bottom: 22px; }
        .top-left, .top-right { display: table-cell; vertical-align: top; }
        .top-right { text-align: right; }
        .tenant-name { font-size: 16px; font-weight: 700; color: #1f1f1f; margin-bottom: 4px; }
        .tenant-meta { font-size: 10px; color: #595959; line-height: 1.5; }
        .doc-title { font-size: 22px; font-weight: 700; letter-spacing: 1px; color: #1677ff; margin-bottom: 4px; }
        .doc-number { font-size: 13px; font-weight: 600; color: #262626; }
        .status-badge { display: inline-block; margin-top: 6px; padding: 4px 12px; border-radius: 4px; font-size: 10px; font-weight: 700; color: #fff; background: {{ $statusColor[$quote->status] ?? '#8c8c8c' }}; }
        .meta-grid { display: table; width: 100%; margin-bottom: 18px; border: 1px solid #e8e8e8; border-radius: 4px; }
        .meta-cell { display: table-cell; padding: 10px 14px; vertical-align: top; width: 50%; }
        .meta-cell + .meta-cell { border-left: 1px solid #e8e8e8; }
        .meta-cell h4 { margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; color: #8c8c8c; font-weight: 600; }
        .meta-cell .name { font-weight: 600; font-size: 12px; color: #262626; margin-bottom: 2px; }
        .meta-cell .sub  { color: #595959; font-size: 10px; }
        .dates { display: table; width: 100%; margin-bottom: 16px; }
        .date-cell { display: table-cell; padding: 6px 14px; background: #fafafa; border-radius: 4px; }
        .date-cell .label { font-size: 9px; text-transform: uppercase; color: #8c8c8c; letter-spacing: 0.5px; }
        .date-cell .val   { font-size: 12px; font-weight: 600; color: #262626; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th { background: #fafafa; color: #595959; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e8e8e8; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        table.items td.idx { color: #8c8c8c; width: 28px; }
        table.items td.product { font-weight: 600; }
        .totals { width: 50%; margin-left: 50%; margin-top: 6px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 10px; font-size: 11px; }
        .totals tr.grand td { font-size: 14px; font-weight: 700; border-top: 2px solid #262626; padding-top: 9px; }
        .totals td.label { color: #595959; }
        .totals td.val { text-align: right; font-weight: 600; }
        .footer-block { margin-top: 22px; padding: 12px 14px; background: #fafafa; border-radius: 4px; }
        .footer-block h5 { margin: 0 0 4px 0; font-size: 10px; text-transform: uppercase; color: #8c8c8c; letter-spacing: 0.5px; }
        .footer-block p { margin: 0; font-size: 10px; color: #595959; white-space: pre-wrap; }
        .small-muted { font-size: 9px; color: #bfbfbf; text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="top-left">
            <div class="tenant-name">{{ $tenant->name ?? config('app.name') }}</div>
            <div class="tenant-meta">
                @if ($tenant?->tax_id)    {{ $tenant->tax_id }}<br>@endif
                @if ($tenant?->address)   {{ $tenant->address }}<br>@endif
                @if ($tenant?->email)     {{ $tenant->email }}<br>@endif
                @if ($tenant?->phone)     {{ $tenant->phone }}@endif
            </div>
        </div>
        <div class="top-right">
            <div class="doc-title">COTIZACION</div>
            <div class="doc-number">{{ $quote->reference }}</div>
            <div class="status-badge">{{ $statusLabels[$quote->status] ?? strtoupper($quote->status) }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-cell">
            <h4>Cliente</h4>
            <div class="name">{{ $quote->company?->name ?? '—' }}</div>
            @if ($quote->company?->legal_name)
                <div class="sub">{{ $quote->company->legal_name }}</div>
            @endif
            @if ($quote->company?->tax_id)
                <div class="sub">Tax ID: {{ $quote->company->tax_id }}</div>
            @endif
        </div>
        <div class="meta-cell">
            <h4>Contacto</h4>
            <div class="name">{{ $quote->contact?->name ?? '—' }}</div>
            @if ($quote->contact?->job_title)
                <div class="sub">{{ $quote->contact->job_title }}</div>
            @endif
            @if ($quote->contact?->primary_email)
                <div class="sub">{{ $quote->contact->primary_email }}</div>
            @endif
        </div>
    </div>

    <div class="dates">
        <div class="date-cell">
            <div class="label">Fecha emision</div>
            <div class="val">{{ $fmtDate($quote->issue_date) }}</div>
        </div>
        <div class="date-cell" style="margin-left: 8px">
            <div class="label">Valida hasta</div>
            <div class="val">{{ $fmtDate($quote->valid_until) }}</div>
        </div>
        <div class="date-cell" style="margin-left: 8px">
            <div class="label">Moneda</div>
            <div class="val">{{ $cur }}</div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="r">#</th>
                <th>Descripcion</th>
                <th>SKU</th>
                <th class="r">Cant.</th>
                <th class="r">Precio</th>
                <th class="r">Desc.</th>
                <th class="r">Imp.</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $i => $it)
                <tr>
                    <td class="idx r">{{ $i + 1 }}</td>
                    <td class="product">{{ $it->name ?? $it->product?->name ?? '—' }}</td>
                    <td>{{ $it->sku ?? $it->product?->sku ?? '—' }}</td>
                    <td class="r">{{ $fmtMoney($it->quantity) }}</td>
                    <td class="r">{{ $cur }} {{ $fmtMoney($it->unit_price) }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) ($it->discount_pct ?? 0), 2, '.', ''), '0'), '.') }}%</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) ($it->tax_pct ?? 0), 2, '.', ''), '0'), '.') }}%</td>
                    <td class="r"><strong>{{ $cur }} {{ $fmtMoney($it->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td class="label">Subtotal</td><td class="val">{{ $cur }} {{ $fmtMoney($quote->subtotal) }}</td></tr>
            @if ((float) ($quote->discount_total ?? 0) > 0)
                <tr><td class="label">Descuento</td><td class="val">− {{ $cur }} {{ $fmtMoney($quote->discount_total) }}</td></tr>
            @endif
            <tr><td class="label">Impuestos</td><td class="val">{{ $cur }} {{ $fmtMoney($quote->tax_total) }}</td></tr>
            @if ((float) ($quote->shipping_cost ?? 0) > 0)
                <tr><td class="label">Envio</td><td class="val">{{ $cur }} {{ $fmtMoney($quote->shipping_cost) }}</td></tr>
            @endif
            <tr class="grand"><td class="label">Total</td><td class="val">{{ $cur }} {{ $fmtMoney($quote->grand_total) }}</td></tr>
        </table>
    </div>

    @if ($quote->notes)
        <div class="footer-block">
            <h5>Notas</h5>
            <p>{{ $quote->notes }}</p>
        </div>
    @endif

    <div class="small-muted">
        Generado el {{ now()->format('d/m/Y H:i') }} · {{ $tenant->name ?? config('app.name') }}
    </div>
</div>
</body>
</html>
