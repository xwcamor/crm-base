import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo ExchangeRates. Clon del patron de Discounts.
 */
export const exchangeRatesFilterFields = (t, { currencyOptions = [] } = {}) => [
    { key: 'base_code',      label: t('exchange_rates.filter_base'),  type: 'tags',   options: currencyOptions },
    { key: 'quote_code',     label: t('exchange_rates.filter_quote'), type: 'tags',   options: currencyOptions },
    { key: 'source',         label: t('exchange_rates.source'),       type: 'text' },
    { key: 'is_active',      label: t('exchange_rates.is_active'),    type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'valid_at',       label: t('exchange_rates.valid_at'),     type: 'date_range' },
    { key: 'created_at',     label: t('global.created_at'),           type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),       type: 'switch' },
];

export const exchangeRatesEmptyFilters = () => ({
    base_code: [],
    quote_code: [],
    source: '',
    is_active: null,
    valid_at: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateExchangeRatesFilters = (server) => ({
    base_code:  Array.isArray(server.base_code) ? server.base_code : (server.base_code ? [server.base_code] : []),
    quote_code: Array.isArray(server.quote_code) ? server.quote_code : (server.quote_code ? [server.quote_code] : []),
    source:     server.source || '',
    is_active:  server.is_active ?? null,
    valid_at:   (server.valid_from && server.valid_to)
        ? [dayjs(server.valid_from), dayjs(server.valid_to)]
        : null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

export const exchangeRatesFiltersToQuery = (f) => ({
    base_code:      f.base_code?.length ? f.base_code : undefined,
    quote_code:     f.quote_code?.length ? f.quote_code : undefined,
    source:         f.source || undefined,
    is_active:      f.is_active ?? undefined,
    valid_from:     f.valid_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    valid_to:       f.valid_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const exchangeRatesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.base_code?.length)  parts.push(`${t('exchange_rates.filter_base')}: ${f.base_code.join(', ')}`);
    if (f.quote_code?.length) parts.push(`${t('exchange_rates.filter_quote')}: ${f.quote_code.join(', ')}`);
    if (f.source)             parts.push(`${t('exchange_rates.source')}: ${f.source}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('exchange_rates.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.valid_at)   parts.push(`${t('exchange_rates.valid_at')}: ${f.valid_at[0]?.format('YYYY-MM-DD')} -> ${f.valid_at[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at) parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

export const serializeSavedFilters = (f) => ({
    base_code:  f.base_code ?? [],
    quote_code: f.quote_code ?? [],
    source:     f.source ?? '',
    is_active:  f.is_active ?? null,
    valid_at:   f.valid_at?.[0]
        ? [f.valid_at[0].format('YYYY-MM-DD'), f.valid_at[1]?.format('YYYY-MM-DD')]
        : null,
    created_at: f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    base_code:  Array.isArray(f.base_code) ? f.base_code : [],
    quote_code: Array.isArray(f.quote_code) ? f.quote_code : [],
    source:     f.source ?? '',
    is_active:  f.is_active ?? null,
    valid_at:   f.valid_at?.[0] ? [dayjs(f.valid_at[0]), dayjs(f.valid_at[1])] : null,
    created_at: f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
