<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /*
         | Reglas para PDFs en DomPDF (clon del template de Customers):
         |
         | 1) font-family: solo "Helvetica" (PDF core font).
         | 2) font-weight: SOLO `normal` o `bold`. Numericos como 600 no andan.
         | 3) Evitar `position: fixed` con offsets negativos para hdrs/footers.
         */

        @page {
            margin: 24px 28px 24px 28px;
        }

        body {
            font-family: Helvetica;
            font-size: 9pt;
            color: #32363A;
            margin: 0;
        }

        .brand-band {
            background: #354A5F;
            color: #ffffff;
            padding: 14px 18px;
            margin-bottom: 14px;
        }
        .brand-band__meta {
            float: right;
            font-size: 8pt;
            color: #cbd5e1;
            text-align: right;
            line-height: 1.4;
        }
        .brand-band__meta strong { color: #ffffff; font-weight: bold; }
        .brand-band__title {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.01em;
        }
        .brand-band__sub {
            font-size: 8pt;
            color: #cbd5e1;
            margin: 4px 0 0 0;
        }

        .filters {
            background: #F0F6FB;
            border-left: 3px solid #0A6ED1;
            padding: 8px 12px;
            margin: 0 0 12px 0;
            font-size: 8.5pt;
            color: #334155;
        }
        .filters__title {
            display: block;
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0A6ED1;
            margin: 0 0 4px 0;
        }
        .filters__list { margin: 0; padding: 0; list-style: none; }
        .filters__list li { line-height: 1.5; }
        .filters__list li b { font-weight: bold; color: #1f2937; }

        .counter {
            font-size: 8.5pt;
            color: #6A6D70;
            margin: 0 0 8px 0;
        }
        .counter strong { color: #1f2937; font-weight: bold; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        table.data thead th {
            background: #0A6ED1;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #085CAF;
        }
        table.data tbody td {
            padding: 5px 8px;
            border: 1px solid #E5E5E5;
            font-size: 8.5pt;
            color: #32363A;
        }
        table.data tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }

        .status-active   { color: #1D7044; font-weight: bold; }
        .status-inactive { color: #C8281D; font-weight: bold; }

        .empty {
            text-align: center;
            padding: 32px 20px;
            color: #6A6D70;
            font-size: 9pt;
        }
        .doc-footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #E5E5E5;
            font-size: 7.5pt;
            color: #6A6D70;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="brand-band">
        <div class="brand-band__meta">
            <strong>{{ config('app.name') }}</strong><br>
            {{ __('global.created_by') }}: {{ $generatedBy }}
        </div>
        <h1 class="brand-band__title">{{ $title }}</h1>
        <p class="brand-band__sub">
            {{ __('global.generated_at') }}: {{ now()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }}
        </p>
    </div>

    @if (!empty($filtersSummary))
        <div class="filters">
            <span class="filters__title">{{ __('global.filters_applied') }}</span>
            <ul class="filters__list">
                @foreach ($filtersSummary as $f)
                    <li><b>{{ $f['label'] }}:</b> {{ $f['value'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="counter">
        {{ trans_choice('global.records_in_report', $totalCount, ['count' => $totalCount]) }}
    </p>

    @php
        $headings = [
            'id'                  => __('discounts.id'),
            'code'                => __('discounts.code'),
            'name'                => __('discounts.name'),
            'description'         => __('discounts.description'),
            'type'                => __('discounts.type'),
            'value'               => __('discounts.value'),
            'currency_code'       => __('discounts.currency'),
            'min_purchase_amount' => __('discounts.min_purchase_amount'),
            'usage_limit'         => __('discounts.usage_limit'),
            'usage_per_customer'  => __('discounts.usage_per_customer'),
            'usage_count'         => __('discounts.usage_count'),
            'valid_from'          => __('discounts.valid_from'),
            'valid_until'         => __('discounts.valid_until'),
            'is_active'           => __('discounts.is_active'),
            'slug'                => 'Slug',
            'created_at'          => __('global.created_at'),
            'updated_at'          => __('global.updated_at'),
            'creator'             => __('global.created_by'),
        ];
    @endphp

    @if ($discounts->count() === 0)
        <div class="empty">
            {{ __('global.no_matching_records') }}
        </div>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $headings[$col] ?? $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($discounts as $discount)
                    <tr>
                        @foreach ($columns as $col)
                            <td>
                                @switch($col)
                                    @case('id')                  {{ $discount->id }} @break
                                    @case('code')                {{ $discount->code }} @break
                                    @case('name')                {{ $discount->name }} @break
                                    @case('description')         {{ $discount->description ?? '' }} @break
                                    @case('type')                {{ $discount->type_text }} @break
                                    @case('value')               {{ $discount->value }} @break
                                    @case('currency_code')       {{ $discount->currency_code ?? '' }} @break
                                    @case('min_purchase_amount') {{ $discount->min_purchase_amount ?? '' }} @break
                                    @case('usage_limit')         {{ $discount->usage_limit ?? '' }} @break
                                    @case('usage_per_customer')  {{ $discount->usage_per_customer ?? '' }} @break
                                    @case('usage_count')         {{ $discount->usage_count }} @break
                                    @case('valid_from')          {{ $discount->valid_from?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) ?? '' }} @break
                                    @case('valid_until')         {{ $discount->valid_until?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) ?? '' }} @break
                                    @case('is_active')
                                        <span class="{{ $discount->is_active ? 'status-active' : 'status-inactive' }}">
                                            {{ $discount->state_text }}
                                        </span>
                                    @break
                                    @case('slug')       {{ $discount->slug }} @break
                                    @case('created_at') {{ $discount->created_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('updated_at') {{ $discount->updated_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('creator')    {{ $discount->creator->name ?? '—' }} @break
                                    @default {{ $discount->{$col} ?? '' }}
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="doc-footer">
        {{ config('app.name') }} · {{ now()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATE_FORMAT) }}
    </div>
</body>
</html>
