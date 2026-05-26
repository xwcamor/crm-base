import dayjs from 'dayjs';

export const leadSourcesFilterFields = (t) => [
    { key: 'name',           label: t('lead_sources.filter_name'), type: 'tags' },
    { key: 'category',       label: t('lead_sources.category'),    type: 'text' },
    { key: 'is_active',      label: t('lead_sources.is_active'),   type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),        type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),    type: 'switch' },
];

export const leadSourcesEmptyFilters = () => ({
    name: [],
    category: '',
    is_active: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateLeadSourcesFilters = (server) => ({
    name:       Array.isArray(server.name) ? server.name : (server.name ? [server.name] : []),
    category:   server.category || '',
    is_active:  server.is_active ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

export const leadSourcesFiltersToQuery = (f) => ({
    name:           f.name?.length ? f.name : undefined,
    category:       f.category || undefined,
    is_active:      f.is_active ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const leadSourcesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('lead_sources.filter_name')}: ${f.name.join(', ')}`);
    if (f.category)     parts.push(`${t('lead_sources.category')}: ${f.category}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('lead_sources.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at)   parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    name:           f.name ?? [],
    category:       f.category ?? '',
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:           Array.isArray(f.name) ? f.name : [],
    category:       f.category ?? '',
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
