import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Invoices. La config vive aca,
 * no hardcodeada en Index.vue. Mismo patron que SalesOrders/Customers.
 *
 * Filtros expuestos:
 *  - number:         busqueda por N° de factura (LIKE)
 *  - status:         enum (draft/sent/paid/partial/overdue/cancelled/refunded)
 *  - company_id:     cliente al que se le facturo
 *  - issue_date:     rango de emision
 *  - due_date:       rango de vencimiento
 *  - only_overdue:   solo vencidas (due_date < hoy AND balance_due > 0)
 *  - only_unpaid:    solo con saldo pendiente (balance_due > 0)
 *  - only_favorites: solo favoritas del usuario
 */
export const invoicesFilterFields = (t, { companyOptions = [], statusOptions = [] } = {}) => [
    { key: 'number',         label: t('invoices.filter_number'), type: 'text' },
    { key: 'status',         label: t('invoices.status'),        type: 'select', options: statusOptions },
    { key: 'company_id',     label: t('invoices.company'),       type: 'select', options: companyOptions, showSearch: true },
    { key: 'issue_date',     label: t('invoices.issue_date'),    type: 'date_range' },
    { key: 'due_date',       label: t('invoices.due_date'),      type: 'date_range' },
    { key: 'only_overdue',   label: t('invoices.only_overdue'),  type: 'switch' },
    { key: 'only_unpaid',    label: t('invoices.only_unpaid'),   type: 'switch' },
    { key: 'only_favorites', label: t('global.only_favorites'),  type: 'switch' },
];

/** Estado vacio del form de filtros (tambien usado por clearFilters). */
export const invoicesEmptyFilters = () => ({
    number:         '',
    status:         null,
    company_id:     null,
    issue_date:     null,
    due_date:       null,
    only_overdue:   false,
    only_unpaid:    false,
    only_favorites: false,
});

/** Backend payload -> form local (dates ISO -> dayjs). */
export const hydrateInvoicesFilters = (server) => ({
    number:     server.number ?? '',
    status:     server.status || null,
    company_id: server.company_id ?? null,
    issue_date: (server.issue_from && server.issue_to)
        ? [dayjs(server.issue_from), dayjs(server.issue_to)]
        : null,
    due_date:   (server.due_from && server.due_to)
        ? [dayjs(server.due_from), dayjs(server.due_to)]
        : null,
    only_overdue:   server.only_overdue ?? false,
    only_unpaid:    server.only_unpaid ?? false,
    only_favorites: server.only_favorites ?? false,
});

/** Form local -> request params para Inertia reload. */
export const invoicesFiltersToQuery = (f) => ({
    number:         f.number || undefined,
    status:         f.status || undefined,
    company_id:     f.company_id ?? undefined,
    issue_from:     f.issue_date?.[0]?.format('YYYY-MM-DD') ?? undefined,
    issue_to:       f.issue_date?.[1]?.format('YYYY-MM-DD') ?? undefined,
    due_from:       f.due_date?.[0]?.format('YYYY-MM-DD') ?? undefined,
    due_to:         f.due_date?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_overdue:   f.only_overdue   ? 1 : undefined,
    only_unpaid:    f.only_unpaid    ? 1 : undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const invoicesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.number)                         parts.push(`${t('invoices.filter_number')}: ${f.number}`);
    if (f.status)                         parts.push(`${t('invoices.status')}: ${t('invoices.status_options.' + f.status)}`);
    if (f.issue_date)                     parts.push(`${t('invoices.issue_date')}: ${f.issue_date[0]?.format('YYYY-MM-DD')} -> ${f.issue_date[1]?.format('YYYY-MM-DD')}`);
    if (f.due_date)                       parts.push(`${t('invoices.due_date')}: ${f.due_date[0]?.format('YYYY-MM-DD')} -> ${f.due_date[1]?.format('YYYY-MM-DD')}`);
    if (f.only_overdue)                   parts.push(t('invoices.only_overdue'));
    if (f.only_unpaid)                    parts.push(t('invoices.only_unpaid'));
    if (f.only_favorites)                 parts.push(t('global.only_favorites'));
    return parts.join(' / ');
};

/**
 * Serializacion de filtros para Saved Views (JSON-safe: dayjs -> ISO strings).
 * Round-trip con `deserializeSavedFilters`.
 */
export const serializeSavedFilters = (f) => ({
    number:     f.number ?? '',
    status:     f.status ?? null,
    company_id: f.company_id ?? null,
    issue_date: f.issue_date?.[0]
        ? [f.issue_date[0].format('YYYY-MM-DD'), f.issue_date[1]?.format('YYYY-MM-DD')]
        : null,
    due_date:   f.due_date?.[0]
        ? [f.due_date[0].format('YYYY-MM-DD'), f.due_date[1]?.format('YYYY-MM-DD')]
        : null,
    only_overdue:   !!f.only_overdue,
    only_unpaid:    !!f.only_unpaid,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    number:     f.number ?? '',
    status:     f.status ?? null,
    company_id: f.company_id ?? null,
    issue_date: f.issue_date?.[0] ? [dayjs(f.issue_date[0]), dayjs(f.issue_date[1])] : null,
    due_date:   f.due_date?.[0]   ? [dayjs(f.due_date[0]),   dayjs(f.due_date[1])]   : null,
    only_overdue:   f.only_overdue   ?? false,
    only_unpaid:    f.only_unpaid    ?? false,
    only_favorites: f.only_favorites ?? false,
});
