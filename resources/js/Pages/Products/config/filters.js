import dayjs from 'dayjs';

/**
 * Schema de filtros del módulo Products.
 */
export const productsFilterFields = (t, { categoryOptions = [], typeOptions = [], currencyOptions = [] } = {}) => [
    { key: 'name',           label: t('products.filter_name'),   type: 'tags' },
    { key: 'sku',            label: t('products.sku'),           type: 'text' },
    { key: 'type',           label: t('products.type'),          type: 'select', options: typeOptions },
    { key: 'category_id',    label: t('products.category'),      type: 'select', options: categoryOptions },
    { key: 'brand',          label: t('products.brand'),         type: 'text' },
    { key: 'currency_code',  label: t('products.currency'),      type: 'select', options: currencyOptions },
    { key: 'price_from',     label: t('products.list_price') + ' ≥', type: 'number' },
    { key: 'price_to',       label: t('products.list_price') + ' ≤', type: 'number' },
    { key: 'is_active',      label: t('products.is_active'),     type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),      type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),  type: 'switch' },
];

/** Estado vacío del form de filtros (también usado por clearFilters). */
export const productsEmptyFilters = () => ({
    name: [],
    sku: '',
    type: null,
    category_id: null,
    brand: '',
    currency_code: null,
    price_from: null,
    price_to: null,
    is_active: null,
    created_at: null,
    only_favorites: false,
});

/** Backend payload → form local (dates ISO → dayjs). */
export const hydrateProductsFilters = (server) => ({
    name:          Array.isArray(server.name) ? server.name : [],
    sku:           server.sku ?? '',
    type:          server.type ?? null,
    category_id:   server.category_id ?? null,
    brand:         server.brand ?? '',
    currency_code: server.currency_code ?? null,
    price_from:    server.price_from ?? null,
    price_to:      server.price_to ?? null,
    is_active:     server.is_active ?? null,
    created_at:    (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

/** Form local → request params para Inertia reload. */
export const productsFiltersToQuery = (f) => ({
    name:          f.name?.length ? f.name : undefined,
    sku:           f.sku || undefined,
    type:          f.type ?? undefined,
    category_id:   f.category_id ?? undefined,
    brand:         f.brand || undefined,
    currency_code: f.currency_code ?? undefined,
    price_from:    f.price_from ?? undefined,
    price_to:      f.price_to ?? undefined,
    is_active:     f.is_active ?? undefined,
    created_from:  f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:    f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const productsFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length)        parts.push(`${t('products.filter_name')}: ${f.name.join(', ')}`);
    if (f.sku)                 parts.push(`${t('products.sku')}: ${f.sku}`);
    if (f.type)                parts.push(`${t('products.type')}: ${f.type}`);
    if (f.brand)               parts.push(`${t('products.brand')}: ${f.brand}`);
    if (f.currency_code)       parts.push(`${t('products.currency')}: ${f.currency_code}`);
    if (f.price_from != null)  parts.push(`${t('products.list_price')} ≥ ${f.price_from}`);
    if (f.price_to != null)    parts.push(`${t('products.list_price')} ≤ ${f.price_to}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('products.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at)          parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

/**
 * Serialización de filtros para Saved Views (JSON-safe: dayjs → ISO strings).
 * Round-trip con `deserializeSavedFilters`.
 */
export const serializeSavedFilters = (f) => ({
    name:          f.name ?? [],
    sku:           f.sku ?? '',
    type:          f.type ?? null,
    category_id:   f.category_id ?? null,
    brand:         f.brand ?? '',
    currency_code: f.currency_code ?? null,
    price_from:    f.price_from ?? null,
    price_to:      f.price_to ?? null,
    is_active:     f.is_active ?? null,
    created_at:    f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:          Array.isArray(f.name) ? f.name : [],
    sku:           f.sku ?? '',
    type:          f.type ?? null,
    category_id:   f.category_id ?? null,
    brand:         f.brand ?? '',
    currency_code: f.currency_code ?? null,
    price_from:    f.price_from ?? null,
    price_to:      f.price_to ?? null,
    is_active:     f.is_active ?? null,
    created_at:    f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
