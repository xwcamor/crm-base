import dayjs from 'dayjs';

export const paymentMethodsFilterFields = (t) => [
    { key: 'name',                 label: t('payment_methods.filter_name'),            type: 'tags' },
    { key: 'code',                 label: t('payment_methods.code'),                   type: 'text' },
    { key: 'integration_provider', label: t('payment_methods.integration_provider'),   type: 'text' },
    { key: 'is_active',            label: t('payment_methods.is_active'),              type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'requires_reference',   label: t('payment_methods.requires_reference'),     type: 'select', options: [
        { value: true,  label: t('global.yes') },
        { value: false, label: t('global.no')  },
    ]},
    { key: 'created_at',           label: t('global.created_at'),                      type: 'date_range' },
    { key: 'only_favorites',       label: t('global.only_favorites'),                  type: 'switch' },
];

export const paymentMethodsEmptyFilters = () => ({
    name: [],
    code: '',
    integration_provider: '',
    is_active: null,
    requires_reference: null,
    created_at: null,
    only_favorites: false,
});

export const hydratePaymentMethodsFilters = (server) => ({
    name:                 Array.isArray(server.name) ? server.name : (server.name ? [server.name] : []),
    code:                 server.code ?? '',
    integration_provider: server.integration_provider ?? '',
    is_active:            server.is_active ?? null,
    requires_reference:   server.requires_reference ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites:       server.only_favorites ?? false,
});

export const paymentMethodsFiltersToQuery = (f) => ({
    name:                 f.name?.length ? f.name : undefined,
    code:                 f.code || undefined,
    integration_provider: f.integration_provider || undefined,
    is_active:            f.is_active ?? undefined,
    requires_reference:   f.requires_reference ?? undefined,
    created_from:         f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:           f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites:       f.only_favorites ? 1 : undefined,
});

export const paymentMethodsFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('payment_methods.filter_name')}: ${f.name.join(', ')}`);
    if (f.code)         parts.push(`${t('payment_methods.code')}: ${f.code}`);
    if (f.integration_provider) parts.push(`${t('payment_methods.integration_provider')}: ${f.integration_provider}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('payment_methods.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.requires_reference !== null && f.requires_reference !== undefined) {
        parts.push(`${t('payment_methods.requires_reference')}: ${f.requires_reference ? t('global.yes') : t('global.no')}`);
    }
    if (f.created_at)   parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    name:                 f.name ?? [],
    code:                 f.code ?? '',
    integration_provider: f.integration_provider ?? '',
    is_active:            f.is_active ?? null,
    requires_reference:   f.requires_reference ?? null,
    created_at:           f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites:       !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:                 Array.isArray(f.name) ? f.name : [],
    code:                 f.code ?? '',
    integration_provider: f.integration_provider ?? '',
    is_active:            f.is_active ?? null,
    requires_reference:   f.requires_reference ?? null,
    created_at:           f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites:       f.only_favorites ?? false,
});
