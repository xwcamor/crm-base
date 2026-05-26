import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo StockTakes. Acepta listas de opciones para
 * los multiselect (warehouses, statuses).
 */
export const stockTakesFilterFields = (t, {
    warehouseOptions = [], statusOptions = [],
} = {}) => [
    { key: 'reference',      label: t('stock_takes.reference'),  type: 'tags' },
    { key: 'status',         label: t('stock_takes.status'),     type: 'multiselect', options: statusOptions },
    { key: 'warehouse_id',   label: t('stock_takes.warehouse'),  type: 'multiselect', options: warehouseOptions },
    { key: 'started_at',     label: t('stock_takes.started_at'), type: 'date_range' },
    { key: 'created_at',     label: t('global.created_at'),      type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),  type: 'switch' },
];

export const stockTakesEmptyFilters = () => ({
    reference: [],
    status: [],
    warehouse_id: [],
    started_at: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateStockTakesFilters = (server) => ({
    reference:      Array.isArray(server.reference) ? server.reference : (server.reference ? [server.reference] : []),
    status:         Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    warehouse_id:   Array.isArray(server.warehouse_id) ? server.warehouse_id : (server.warehouse_id ? [server.warehouse_id] : []),
    started_at:     (server.started_from && server.started_to) ? [dayjs(server.started_from), dayjs(server.started_to)] : null,
    created_at:     (server.created_from && server.created_to) ? [dayjs(server.created_from), dayjs(server.created_to)] : null,
    only_favorites: server.only_favorites ?? false,
});

export const stockTakesFiltersToQuery = (f) => ({
    reference:      f.reference?.length ? f.reference : undefined,
    status:         f.status?.length ? f.status : undefined,
    warehouse_id:   f.warehouse_id?.length ? f.warehouse_id : undefined,
    started_from:   f.started_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    started_to:     f.started_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const stockTakesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference?.length)    parts.push(`${t('stock_takes.reference')}: ${f.reference.join(', ')}`);
    if (f.status?.length)       parts.push(`${t('stock_takes.status')}: ${f.status.length}`);
    if (f.warehouse_id?.length) parts.push(`${t('stock_takes.warehouse')}: ${f.warehouse_id.length}`);
    if (f.started_at)           parts.push(`${t('stock_takes.started_at')}: ${f.started_at[0]?.format('YYYY-MM-DD')} -> ${f.started_at[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at)           parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    reference:      f.reference ?? [],
    status:         f.status ?? [],
    warehouse_id:   f.warehouse_id ?? [],
    started_at:     f.started_at?.[0] ? [f.started_at[0].format('YYYY-MM-DD'), f.started_at[1]?.format('YYYY-MM-DD')] : null,
    created_at:     f.created_at?.[0] ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')] : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:      Array.isArray(f.reference) ? f.reference : [],
    status:         Array.isArray(f.status) ? f.status : [],
    warehouse_id:   Array.isArray(f.warehouse_id) ? f.warehouse_id : [],
    started_at:     f.started_at?.[0] ? [dayjs(f.started_at[0]), dayjs(f.started_at[1])] : null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
