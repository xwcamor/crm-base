<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 28px 24px 28px; }
        body { font-family: Helvetica; font-size: 9pt; color: #32363A; margin: 0; }
        .brand-band { background: #354A5F; color: #ffffff; padding: 14px 18px; margin-bottom: 14px; }
        .brand-band__meta { float: right; font-size: 8pt; color: #cbd5e1; text-align: right; line-height: 1.4; }
        .brand-band__meta strong { color: #ffffff; font-weight: bold; }
        .brand-band__title { font-size: 14pt; font-weight: bold; margin: 0; letter-spacing: 0.01em; }
        .brand-band__sub { font-size: 8pt; color: #cbd5e1; margin: 4px 0 0 0; }
        .filters { background: #F0F6FB; border-left: 3px solid #0A6ED1; padding: 8px 12px; margin: 0 0 12px 0; font-size: 8.5pt; color: #334155; }
        .filters__title { display: block; font-weight: bold; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: #0A6ED1; margin: 0 0 4px 0; }
        .filters__list { margin: 0; padding: 0; list-style: none; }
        .filters__list li { line-height: 1.5; }
        .filters__list li b { font-weight: bold; color: #1f2937; }
        .counter { font-size: 8.5pt; color: #6A6D70; margin: 0 0 8px 0; }
        .counter strong { color: #1f2937; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin: 0; }
        table.data thead th { background: #0A6ED1; color: #ffffff; font-weight: bold; font-size: 9pt; text-align: left; padding: 6px 8px; border: 1px solid #085CAF; }
        table.data tbody td { padding: 5px 8px; border: 1px solid #E5E5E5; font-size: 8.5pt; color: #32363A; }
        table.data tbody tr:nth-child(even) td { background: #F8FAFC; }
        .status-draft       { color: #6A6D70; font-weight: bold; }
        .status-in_progress { color: #0A6ED1; font-weight: bold; }
        .status-completed   { color: #1D7044; font-weight: bold; }
        .status-cancelled   { color: #C8281D; font-weight: bold; }
        .right { text-align: right; }
        .empty { text-align: center; padding: 32px 20px; color: #6A6D70; font-size: 9pt; }
        .doc-footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #E5E5E5; font-size: 7.5pt; color: #6A6D70; text-align: center; }
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
            'id'           => __('stock_takes.id'),
            'reference'    => __('stock_takes.reference'),
            'warehouse'    => __('stock_takes.warehouse'),
            'status'       => __('stock_takes.status'),
            'started_at'   => __('stock_takes.started_at'),
            'completed_at' => __('stock_takes.completed_at'),
            'note'         => __('stock_takes.note'),
            'slug'         => 'Slug',
            'created_at'   => __('global.created_at'),
            'updated_at'   => __('global.updated_at'),
            'creator'      => __('global.created_by'),
        ];
        $statusClass = fn ($s) => 'status-' . $s;
    @endphp

    @if ($takes->count() === 0)
        <div class="empty">{{ __('global.no_matching_records') }}</div>
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
                @foreach ($takes as $take)
                    <tr>
                        @foreach ($columns as $col)
                            <td>
                                @switch($col)
                                    @case('id')           {{ $take->id }} @break
                                    @case('reference')    {{ $take->reference }} @break
                                    @case('warehouse')    {{ $take->warehouse?->name ?? '—' }} @break
                                    @case('status')
                                        <span class="{{ $statusClass($take->status) }}">{{ __('stock_takes.status_options.' . $take->status) }}</span>
                                    @break
                                    @case('started_at')   {{ $take->started_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('completed_at') {{ $take->completed_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('note')         {{ $take->note }} @break
                                    @case('slug')         {{ $take->slug }} @break
                                    @case('created_at')   {{ $take->created_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('updated_at')   {{ $take->updated_at?->copy()->setTimezone($tz ?? config('app.timezone'))->format(\App\Support\Tz::DATETIME_FORMAT) }} @break
                                    @case('creator')      {{ $take->creator->name ?? '—' }} @break
                                    @default {{ $take->{$col} ?? '' }}
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
