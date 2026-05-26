import dayjs from 'dayjs';

export const productVariantsFilterFields = (t, { productOptions = [] } = {}) => [
    { key: 'name',           label: t('product_variants.filter_name'),  type: 'tags' },
    { key: 'sku',            label: t('product_variants.filter_sku'),   type: 'text' },
    { key: 'product_id',     label: t('product_variants.product'),       type: 'select', options: productOptions, allowClear: true },
    { key: 'is_active',      label: t('product_variants.is_active'),     type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),              type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),          type: 'switch' },
];

export const productVariantsEmptyFilters = () => ({
    name: [],
    sku: '',
    product_id: null,
    is_active: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateProductVariantsFilters = (server) => ({
    name:       Array.isArray(server.name) ? server.name : (server.name ? [server.name] : []),
    sku:        server.sku ?? '',
    product_id: server.product_id || null,
    is_active:  server.is_active ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

export const productVariantsFiltersToQuery = (f) => ({
    name:           f.name?.length ? f.name : undefined,
    sku:            f.sku || undefined,
    product_id:     f.product_id || undefined,
    is_active:      f.is_active ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const productVariantsFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('product_variants.filter_name')}: ${f.name.join(', ')}`);
    if (f.sku)          parts.push(`${t('product_variants.filter_sku')}: ${f.sku}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('product_variants.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at)   parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    name:           f.name ?? [],
    sku:            f.sku ?? '',
    product_id:     f.product_id ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:           Array.isArray(f.name) ? f.name : [],
    sku:            f.sku ?? '',
    product_id:     f.product_id ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
