import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Messages. Mismo patron que Discounts.
 */
export const messagesFilterFields = (t, { audienceOptions = [] } = {}) => [
    { key: 'subject',        label: t('messages.filter_subject'),  type: 'tags' },
    { key: 'audience_type',  label: t('messages.audience_type'),   type: 'select', options: audienceOptions },
    { key: 'is_active',      label: t('messages.is_active'),       type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'allow_replies',  label: t('messages.allow_replies'),   type: 'select', options: [
        { value: true,  label: t('global.yes') },
        { value: false, label: t('global.no')  },
    ]},
    { key: 'created_at',     label: t('global.created_at'),        type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),    type: 'switch' },
];

/** Estado vacio del form de filtros (tambien usado por clearFilters). */
export const messagesEmptyFilters = () => ({
    subject: [],
    audience_type: null,
    is_active: null,
    allow_replies: null,
    created_at: null,
    only_favorites: false,
});

/** Backend payload → form local (dates ISO → dayjs). */
export const hydrateMessagesFilters = (server) => ({
    subject:       Array.isArray(server.subject) ? server.subject : (server.subject ? [server.subject] : []),
    audience_type: server.audience_type || null,
    is_active:     server.is_active ?? null,
    allow_replies: server.allow_replies ?? null,
    created_at:    (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

/** Form local → request params para Inertia reload. */
export const messagesFiltersToQuery = (f) => ({
    subject:        f.subject?.length ? f.subject : undefined,
    audience_type:  f.audience_type || undefined,
    is_active:      f.is_active ?? undefined,
    allow_replies:  f.allow_replies ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const messagesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.subject?.length)        parts.push(`${t('messages.filter_subject')}: ${f.subject.join(', ')}`);
    if (f.audience_type)          parts.push(`${t('messages.audience_type')}: ${t('messages.audience_' + f.audience_type)}`);
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('messages.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at) parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

/** Serializacion de filtros para Saved Views (JSON-safe: dayjs → ISO strings). */
export const serializeSavedFilters = (f) => ({
    subject:        f.subject ?? [],
    audience_type:  f.audience_type ?? null,
    is_active:      f.is_active ?? null,
    allow_replies:  f.allow_replies ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    subject:        Array.isArray(f.subject) ? f.subject : [],
    audience_type:  f.audience_type ?? null,
    is_active:      f.is_active ?? null,
    allow_replies:  f.allow_replies ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
