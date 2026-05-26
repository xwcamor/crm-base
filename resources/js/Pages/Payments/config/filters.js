import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Payments. Mismo patron que Customer master:
 * la config vive aca, no hardcodeada en Index.vue. Recibe `t` (i18n) y las
 * opciones (status/type/method) que vienen del controller.
 */
export const paymentsFilterFields = (t, { statusOptions = [], typeOptions = [], methodOptions = [] } = {}) => [
    { key: 'reference',      label: t('payments.reference'),       type: 'text' },
    { key: 'status',         label: t('payments.status'),          type: 'multiselect', options: statusOptions },
    { key: 'type',           label: t('payments.type'),            type: 'multiselect', options: typeOptions },
    { key: 'payment_method_id', label: t('payments.payment_method'), type: 'multiselect', options: methodOptions },
    { key: 'paid_at',        label: t('payments.paid_at'),         type: 'date_range' },
    { key: 'amount',         label: t('payments.amount'),          type: 'number_range' },
    { key: 'created_at',     label: t('global.created_at'),        type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),    type: 'switch' },
];

/** Estado vacio del form de filtros (tambien usado por clearFilters). */
export const paymentsEmptyFilters = () => ({
    reference: '',
    status: [],
    type: [],
    payment_method_id: [],
    paid_at: null,
    amount: null,
    created_at: null,
    only_favorites: false,
});

/** Backend payload -> form local (dates ISO -> dayjs). */
export const hydratePaymentsFilters = (server) => ({
    reference:         server.reference || '',
    status:            Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    type:              Array.isArray(server.type) ? server.type : (server.type ? [server.type] : []),
    payment_method_id: Array.isArray(server.payment_method_id) ? server.payment_method_id : [],
    paid_at:           (server.paid_from && server.paid_to)
        ? [dayjs(server.paid_from), dayjs(server.paid_to)]
        : null,
    amount:            (server.amount_min != null || server.amount_max != null)
        ? [server.amount_min ?? null, server.amount_max ?? null]
        : null,
    created_at:        (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites:    server.only_favorites ?? false,
});

/** Form local -> request params para Inertia reload. */
export const paymentsFiltersToQuery = (f) => ({
    reference:         f.reference || undefined,
    status:            f.status?.length ? f.status : undefined,
    type:              f.type?.length ? f.type : undefined,
    payment_method_id: f.payment_method_id?.length ? f.payment_method_id : undefined,
    paid_from:         f.paid_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    paid_to:           f.paid_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    amount_min:        f.amount?.[0] ?? undefined,
    amount_max:        f.amount?.[1] ?? undefined,
    created_from:      f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:        f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites:    f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const paymentsFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference)         parts.push(`${t('payments.reference')}: ${f.reference}`);
    if (f.status?.length)    parts.push(`${t('payments.status')}: ${f.status.join(', ')}`);
    if (f.type?.length)      parts.push(`${t('payments.type')}: ${f.type.join(', ')}`);
    if (f.payment_method_id?.length) parts.push(`${t('payments.payment_method')}: ${f.payment_method_id.length}`);
    if (f.paid_at)           parts.push(`${t('payments.paid_at')}: ${f.paid_at[0]?.format('YYYY-MM-DD')} -> ${f.paid_at[1]?.format('YYYY-MM-DD')}`);
    if (f.amount?.[0] != null || f.amount?.[1] != null) {
        parts.push(`${t('payments.amount')}: ${f.amount[0] ?? ''} - ${f.amount[1] ?? ''}`);
    }
    if (f.created_at)        parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

/**
 * Serializacion de filtros para Saved Views (JSON-safe: dayjs -> ISO strings).
 * Round-trip con `deserializeSavedFilters`.
 */
export const serializeSavedFilters = (f) => ({
    reference:         f.reference ?? '',
    status:            f.status ?? [],
    type:              f.type ?? [],
    payment_method_id: f.payment_method_id ?? [],
    paid_at:           f.paid_at?.[0]
        ? [f.paid_at[0].format('YYYY-MM-DD'), f.paid_at[1]?.format('YYYY-MM-DD')]
        : null,
    amount:            f.amount ?? null,
    created_at:        f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites:    !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:         f.reference ?? '',
    status:            Array.isArray(f.status) ? f.status : [],
    type:              Array.isArray(f.type) ? f.type : [],
    payment_method_id: Array.isArray(f.payment_method_id) ? f.payment_method_id : [],
    paid_at:           f.paid_at?.[0] ? [dayjs(f.paid_at[0]), dayjs(f.paid_at[1])] : null,
    amount:            Array.isArray(f.amount) ? f.amount : null,
    created_at:        f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites:    f.only_favorites ?? false,
});
