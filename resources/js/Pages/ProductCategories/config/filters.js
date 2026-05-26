import dayjs from 'dayjs';

export const productCategoriesFilterFields = (t, { parentOptions = [] } = {}) => [
    { key: 'name',           label: t('product_categories.filter_name'), type: 'tags' },
    { key: 'parent_id',      label: t('product_categories.parent'),       type: 'select', options: parentOptions, allowClear: true },
    { key: 'is_active',      label: t('product_categories.is_active'),    type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),               type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),           type: 'switch' },
];

export const productCategoriesEmptyFilters = () => ({
    name: [],
    parent_id: null,
    is_active: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateProductCategoriesFilters = (server) => ({
    name:       Array.isArray(server.name) ? server.name : (server.name ? [server.name] : []),
    parent_id:  server.parent_id || null,
    is_active:  server.is_active ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

export const productCategoriesFiltersToQuery = (f) => ({
    name:           f.name?.length ? f.name : undefined,
    parent_id:      f.parent_id || undefined,
    is_active:      f.is_active ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const productCategoriesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('product_categories.filter_name')}: ${f.name.join(', ')}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('product_categories.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at)   parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    name:           f.name ?? [],
    parent_id:      f.parent_id ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:           Array.isArray(f.name) ? f.name : [],
    parent_id:      f.parent_id ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
