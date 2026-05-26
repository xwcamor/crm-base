import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Quotes. Acepta listas de opciones para
 * los multiselect (companies, contacts, deals, statuses).
 */
export const quotesFilterFields = (t, {
    companyOptions = [], contactOptions = [], dealOptions = [],
    statusOptions = [],
} = {}) => [
    { key: 'reference',      label: t('quotes.reference'),      type: 'tags' },
    { key: 'status',         label: t('quotes.status'),         type: 'multiselect', options: statusOptions },
    { key: 'company_id',     label: t('quotes.company'),        type: 'multiselect', options: companyOptions },
    { key: 'contact_id',     label: t('quotes.contact'),        type: 'multiselect', options: contactOptions },
    { key: 'deal_id',        label: t('quotes.deal'),           type: 'multiselect', options: dealOptions },
    { key: 'issue_date',     label: t('quotes.issue_date'),     type: 'date_range' },
    { key: 'valid_until',    label: t('quotes.valid_until'),    type: 'date_range' },
    { key: 'created_at',     label: t('global.created_at'),     type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'), type: 'switch' },
];

export const quotesEmptyFilters = () => ({
    reference: [],
    status: [],
    company_id: [],
    contact_id: [],
    deal_id: [],
    issue_date: null,
    valid_until: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateQuotesFilters = (server) => ({
    reference:      Array.isArray(server.reference) ? server.reference : (server.reference ? [server.reference] : []),
    status:         Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    company_id:     Array.isArray(server.company_id) ? server.company_id : (server.company_id ? [server.company_id] : []),
    contact_id:     Array.isArray(server.contact_id) ? server.contact_id : (server.contact_id ? [server.contact_id] : []),
    deal_id:        Array.isArray(server.deal_id) ? server.deal_id : (server.deal_id ? [server.deal_id] : []),
    issue_date:     (server.issue_from && server.issue_to) ? [dayjs(server.issue_from), dayjs(server.issue_to)] : null,
    valid_until:    (server.valid_from && server.valid_to) ? [dayjs(server.valid_from), dayjs(server.valid_to)] : null,
    created_at:     (server.created_from && server.created_to) ? [dayjs(server.created_from), dayjs(server.created_to)] : null,
    only_favorites: server.only_favorites ?? false,
});

export const quotesFiltersToQuery = (f) => ({
    reference:      f.reference?.length ? f.reference : undefined,
    status:         f.status?.length ? f.status : undefined,
    company_id:     f.company_id?.length ? f.company_id : undefined,
    contact_id:     f.contact_id?.length ? f.contact_id : undefined,
    deal_id:        f.deal_id?.length ? f.deal_id : undefined,
    issue_from:     f.issue_date?.[0]?.format('YYYY-MM-DD') ?? undefined,
    issue_to:       f.issue_date?.[1]?.format('YYYY-MM-DD') ?? undefined,
    valid_from:     f.valid_until?.[0]?.format('YYYY-MM-DD') ?? undefined,
    valid_to:       f.valid_until?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const quotesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference?.length)  parts.push(`${t('quotes.reference')}: ${f.reference.join(', ')}`);
    if (f.status?.length)     parts.push(`${t('quotes.status')}: ${f.status.length}`);
    if (f.company_id?.length) parts.push(`${t('quotes.company')}: ${f.company_id.length}`);
    if (f.contact_id?.length) parts.push(`${t('quotes.contact')}: ${f.contact_id.length}`);
    if (f.deal_id?.length)    parts.push(`${t('quotes.deal')}: ${f.deal_id.length}`);
    if (f.issue_date)         parts.push(`${t('quotes.issue_date')}: ${f.issue_date[0]?.format('YYYY-MM-DD')} -> ${f.issue_date[1]?.format('YYYY-MM-DD')}`);
    if (f.valid_until)        parts.push(`${t('quotes.valid_until')}: ${f.valid_until[0]?.format('YYYY-MM-DD')} -> ${f.valid_until[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at)         parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    reference:      f.reference ?? [],
    status:         f.status ?? [],
    company_id:     f.company_id ?? [],
    contact_id:     f.contact_id ?? [],
    deal_id:        f.deal_id ?? [],
    issue_date:     f.issue_date?.[0] ? [f.issue_date[0].format('YYYY-MM-DD'), f.issue_date[1]?.format('YYYY-MM-DD')] : null,
    valid_until:    f.valid_until?.[0] ? [f.valid_until[0].format('YYYY-MM-DD'), f.valid_until[1]?.format('YYYY-MM-DD')] : null,
    created_at:     f.created_at?.[0] ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')] : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:      Array.isArray(f.reference) ? f.reference : [],
    status:         Array.isArray(f.status) ? f.status : [],
    company_id:     Array.isArray(f.company_id) ? f.company_id : [],
    contact_id:     Array.isArray(f.contact_id) ? f.contact_id : [],
    deal_id:        Array.isArray(f.deal_id) ? f.deal_id : [],
    issue_date:     f.issue_date?.[0] ? [dayjs(f.issue_date[0]), dayjs(f.issue_date[1])] : null,
    valid_until:    f.valid_until?.[0] ? [dayjs(f.valid_until[0]), dayjs(f.valid_until[1])] : null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
