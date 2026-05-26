import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Plans. Plans es super-only (no per-tenant),
 * sin filtro de tenant. Clon del patron de Discounts.
 */
export const plansFilterFields = (t, { supportOptions = [] } = {}) => [
    { key: 'name',          label: t('plans.filter_name'),    type: 'tags' },
    { key: 'support_level', label: t('plans.support_level'), type: 'select', options: supportOptions },
    { key: 'is_active',     label: t('plans.is_active'),     type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'is_public',     label: t('plans.is_public'),     type: 'select', options: [
        { value: true,  label: t('global.yes') },
        { value: false, label: t('global.no')  },
    ]},
    { key: 'created_at',    label: t('global.created_at'),   type: 'date_range' },
];

export const plansEmptyFilters = () => ({
    name: [],
    support_level: null,
    is_active: null,
    is_public: null,
    created_at: null,
});

export const hydratePlansFilters = (server) => ({
    name:          Array.isArray(server.name) ? server.name : (server.name ? [server.name] : []),
    support_level: server.support_level || null,
    is_active:     server.is_active ?? null,
    is_public:     server.is_public ?? null,
    created_at:    (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
});

export const plansFiltersToQuery = (f) => ({
    name:          f.name?.length ? f.name : undefined,
    support_level: f.support_level || undefined,
    is_active:     f.is_active ?? undefined,
    is_public:     f.is_public ?? undefined,
    created_from:  f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:    f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
});

export const plansFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('plans.filter_name')}: ${f.name.join(', ')}`);
    if (f.support_level) parts.push(`${t('plans.support_level')}: ${t('plans.support_' + f.support_level)}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('plans.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.is_public !== null && f.is_public !== undefined) {
        parts.push(`${t('plans.is_public')}: ${f.is_public ? t('global.yes') : t('global.no')}`);
    }
    if (f.created_at) parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

export const serializeSavedFilters = (f) => ({
    name:          f.name ?? [],
    support_level: f.support_level ?? null,
    is_active:     f.is_active ?? null,
    is_public:     f.is_public ?? null,
    created_at:    f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:          Array.isArray(f.name) ? f.name : [],
    support_level: f.support_level ?? null,
    is_active:     f.is_active ?? null,
    is_public:     f.is_public ?? null,
    created_at:    f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
});
