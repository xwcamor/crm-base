@php
    $fmtMoney = fn ($n) => number_format((float) ($n ?? 0), 2, '.', ',');
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    $cur = $invoice->currency_code ?? '';
    $statusLabels = [
        'draft' => 'BORRADOR', 'sent' => 'EMITIDA', 'paid' => 'PAGADA',
        'partial' => 'PAGO PARCIAL', 'overdue' => 'VENCIDA',
        'cancelled' => 'ANULADA', 'refunded' => 'REEMBOLSADA',
    ];
    $statusColor = [
        'paid' => '#52c41a', 'sent' => '#1677ff', 'partial' => '#faad14',
        'overdue' => '#ff4d4f', 'cancelled' => '#8c8c8c', 'refunded' => '#722ed1',
        'draft' => '#8c8c8c',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->number ?? 'Factura' }}</title>
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
        .status-badge {
            display: inline-block; margin-top: 6px; padding: 4px 12px;
            border-radius: 4px; font-size: 10px; font-weight: 700;
            color: #fff;
            background: {{ $statusColor[$invoice->status] ?? '#8c8c8c' }};
        }

        .meta-grid { display: table; width: 100%; margin-bottom: 18px; border: 1px solid #e8e8e8; border-radius: 4px; }
        .meta-cell { display: table-cell; padding: 10px 14px; vertical-align: top; width: 50%; }
        .meta-cell + .meta-cell { border-left: 1px solid #e8e8e8; }
        .meta-cell h4 { margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; color: #8c8c8c; font-weight: 600; }
        .meta-cell .name { font-weight: 600; font-size: 12px; color: #262626; margin-bottom: 2px; }
        .meta-cell .sub  { color: #595959; font-size: 10px; }

        .dates { display: table; width: 100%; margin-bottom: 16px; }
        .date-cell { display: table-cell; padding: 6px 14px; background: #fafafa; border-radius: 4px; }
        .date-cell + .date-cell { padding-left: 14px; }
        .date-cell .label { font-size: 9px; text-transform: uppercase; color: #8c8c8c; letter-spacing: 0.5px; }
        .date-cell .val   { font-size: 12px; font-weight: 600; color: #262626; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th {
            background: #fafafa; color: #595959; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 8px 10px; text-align: left; border-bottom: 2px solid #e8e8e8;
        }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        table.items td.idx { color: #8c8c8c; width: 28px; }
        table.items td.product { font-weight: 600; }
        table.items td.product small { color: #8c8c8c; font-weight: 400; }

        .totals { width: 50%; margin-left: 50%; margin-top: 6px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 10px; font-size: 11px; }
        .totals tr.grand td { font-size: 14px; font-weight: 700; border-top: 2px solid #262626; padding-top: 9px; }
        .totals td.label { color: #595959; }
        .totals td.val { text-align: right; font-weight: 600; }
        .totals tr.paid td { color: #52c41a; }
        .totals tr.balance td { color: #faad14; }

        .footer-block { margin-top: 22px; padding: 12px 14px; background: #fafafa; border-radius: 4px; }
        .footer-block h5 { margin: 0 0 4px 0; font-size: 10px; text-transform: uppercase; color: #8c8c8c; letter-spacing: 0.5px; }
        .footer-block p { margin: 0; font-size: 10px; color: #595959; white-space: pre-wrap; }

        .payments-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .payments-table th, .payments-table td { padding: 6px 8px; font-size: 10px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .payments-table th { color: #8c8c8c; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .payments-table td.r { text-align: right; }

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
            <div class="doc-title">FACTURA</div>
            <div class="doc-number">{{ $invoice->number }}</div>
            <div class="status-badge">{{ $statusLabels[$invoice->status] ?? strtoupper($invoice->status) }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-cell">
            <h4>Facturar a</h4>
            <div class="name">{{ $invoice->company?->name ?? '—' }}</div>
            @if ($invoice->company?->legal_name)
                <div class="sub">{{ $invoice->company->legal_name }}</div>
            @endif
            @if ($invoice->company?->tax_id)
                <div class="sub">Tax ID: {{ $invoice->company->tax_id }}</div>
            @endif
            @if ($invoice->company?->billing_email)
                <div class="sub">{{ $invoice->company->billing_email }}</div>
            @endif
        </div>
        <div class="meta-cell">
            <h4>Contacto</h4>
            <div class="name">{{ $invoice->contact?->name ?? '—' }}</div>
            @if ($invoice->contact?->job_title)
                <div class="sub">{{ $invoice->contact->job_title }}</div>
            @endif
            @if ($invoice->contact?->primary_email)
                <div class="sub">{{ $invoice->contact->primary_email }}</div>
            @endif
            @if ($invoice->contact?->primary_phone)
                <div class="sub">{{ $invoice->contact->primary_phone }}</div>
            @endif
        </div>
    </div>

    <div class="dates">
        <div class="date-cell">
            <div class="label">Fecha emisión</div>
            <div class="val">{{ $fmtDate($invoice->issue_date) }}</div>
        </div>
        <div class="date-cell" style="margin-left: 8px">
            <div class="label">Fecha vencimiento</div>
            <div class="val">{{ $fmtDate($invoice->due_date) }}</div>
        </div>
        @if ($invoice->reference)
            <div class="date-cell" style="margin-left: 8px">
                <div class="label">Referencia</div>
                <div class="val">{{ $invoice->reference }}</div>
            </div>
        @endif
        <div class="date-cell" style="margin-left: 8px">
            <div class="label">Moneda</div>
            <div class="val">{{ $cur }}</div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="r">#</th>
                <th>Descripción</th>
                <th>SKU</th>
                <th class="r">Cant.</th>
                <th class="r">Precio</th>
                <th class="r">Imp.</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $it)
                <tr>
                    <td class="idx r">{{ $i + 1 }}</td>
                    <td class="product">
                        {{ $it->name ?? $it->product?->name ?? '—' }}
                        @if ($it->description)
                            <br><small>{{ $it->description }}</small>
                        @endif
                    </td>
                    <td>{{ $it->sku ?? $it->product?->sku ?? '—' }}</td>
                    <td class="r">{{ $fmtMoney($it->quantity) }}</td>
                    <td class="r">{{ $cur }} {{ $fmtMoney($it->unit_price) }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) ($it->tax_pct ?? 0), 2, '.', ''), '0'), '.') }}%</td>
                    <td class="r"><strong>{{ $cur }} {{ $fmtMoney($it->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td class="label">Subtotal</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->subtotal) }}</td></tr>
            @if ((float) $invoice->discount_total > 0)
                <tr><td class="label">Descuento</td><td class="val">− {{ $cur }} {{ $fmtMoney($invoice->discount_total) }}</td></tr>
            @endif
            <tr><td class="label">Impuestos</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->tax_total) }}</td></tr>
            @if ((float) ($invoice->shipping_cost ?? 0) > 0)
                <tr><td class="label">Envío</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->shipping_cost) }}</td></tr>
            @endif
            <tr class="grand"><td class="label">Total</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->grand_total) }}</td></tr>
            @if ((float) ($invoice->amount_paid ?? 0) > 0)
                <tr class="paid"><td class="label">Pagado</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->amount_paid) }}</td></tr>
                <tr class="balance"><td class="label">Saldo pendiente</td><td class="val">{{ $cur }} {{ $fmtMoney($invoice->balance_due) }}</td></tr>
            @endif
        </table>
    </div>

    @if ($invoice->payments && $invoice->payments->count() > 0)
        <div class="footer-block">
            <h5>Pagos recibidos</h5>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th class="r">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $p)
                        <tr>
                            <td>{{ $fmtDate($p->paid_at) }}</td>
                            <td>{{ $p->paymentMethod?->name ?? '—' }}</td>
                            <td>{{ $p->reference ?? '—' }}</td>
                            <td class="r">{{ $cur }} {{ $fmtMoney($p->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="footer-block">
            <h5>Notas</h5>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    @if ($invoice->payment_terms)
        <div class="footer-block">
            <h5>Términos de pago</h5>
            <p>{{ $invoice->payment_terms }}</p>
        </div>
    @endif

    <div class="small-muted">
        Generado el {{ now()->format('d/m/Y H:i') }} · {{ $tenant->name ?? config('app.name') }}
    </div>
</div>
</body>
</html>
