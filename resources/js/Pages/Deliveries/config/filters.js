import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo Deliveries. Acepta listas de opciones para
 * los multiselect (warehouses, statuses, sales orders).
 */
export const deliveriesFilterFields = (t, {
    warehouseOptions = [], statusOptions = [], salesOrderOptions = [],
} = {}) => [
    { key: 'reference',       label: t('deliveries.reference'),       type: 'tags' },
    { key: 'status',          label: t('deliveries.status'),          type: 'multiselect', options: statusOptions },
    { key: 'sales_order_id',  label: t('deliveries.sales_order'),     type: 'multiselect', options: salesOrderOptions },
    { key: 'warehouse_id',    label: t('deliveries.warehouse'),       type: 'multiselect', options: warehouseOptions },
    { key: 'carrier',         label: t('deliveries.carrier'),         type: 'text' },
    { key: 'tracking_number', label: t('deliveries.tracking_number'), type: 'text' },
    { key: 'shipped_at',      label: t('deliveries.shipped_at'),      type: 'date_range' },
    { key: 'created_at',      label: t('global.created_at'),          type: 'date_range' },
    { key: 'only_favorites',  label: t('global.only_favorites'),      type: 'switch' },
];

export const deliveriesEmptyFilters = () => ({
    reference: [],
    status: [],
    sales_order_id: [],
    warehouse_id: [],
    carrier: '',
    tracking_number: '',
    shipped_at: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateDeliveriesFilters = (server) => ({
    reference:       Array.isArray(server.reference) ? server.reference : (server.reference ? [server.reference] : []),
    status:          Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    sales_order_id:  Array.isArray(server.sales_order_id) ? server.sales_order_id : (server.sales_order_id ? [server.sales_order_id] : []),
    warehouse_id:    Array.isArray(server.warehouse_id) ? server.warehouse_id : (server.warehouse_id ? [server.warehouse_id] : []),
    carrier:         server.carrier ?? '',
    tracking_number: server.tracking_number ?? '',
    shipped_at:      (server.shipped_from && server.shipped_to) ? [dayjs(server.shipped_from), dayjs(server.shipped_to)] : null,
    created_at:      (server.created_from && server.created_to) ? [dayjs(server.created_from), dayjs(server.created_to)] : null,
    only_favorites:  server.only_favorites ?? false,
});

export const deliveriesFiltersToQuery = (f) => ({
    reference:       f.reference?.length ? f.reference : undefined,
    status:          f.status?.length ? f.status : undefined,
    sales_order_id:  f.sales_order_id?.length ? f.sales_order_id : undefined,
    warehouse_id:    f.warehouse_id?.length ? f.warehouse_id : undefined,
    carrier:         f.carrier || undefined,
    tracking_number: f.tracking_number || undefined,
    shipped_from:    f.shipped_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    shipped_to:      f.shipped_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:    f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:      f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites:  f.only_favorites ? 1 : undefined,
});

export const deliveriesFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference?.length)      parts.push(`${t('deliveries.reference')}: ${f.reference.join(', ')}`);
    if (f.status?.length)         parts.push(`${t('deliveries.status')}: ${f.status.length}`);
    if (f.sales_order_id?.length) parts.push(`${t('deliveries.sales_order')}: ${f.sales_order_id.length}`);
    if (f.warehouse_id?.length)   parts.push(`${t('deliveries.warehouse')}: ${f.warehouse_id.length}`);
    if (f.carrier)                parts.push(`${t('deliveries.carrier')}: ${f.carrier}`);
    if (f.tracking_number)        parts.push(`${t('deliveries.tracking_number')}: ${f.tracking_number}`);
    if (f.shipped_at)             parts.push(`${t('deliveries.shipped_at')}: ${f.shipped_at[0]?.format('YYYY-MM-DD')} -> ${f.shipped_at[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at)             parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    reference:       f.reference ?? [],
    status:          f.status ?? [],
    sales_order_id:  f.sales_order_id ?? [],
    warehouse_id:    f.warehouse_id ?? [],
    carrier:         f.carrier ?? '',
    tracking_number: f.tracking_number ?? '',
    shipped_at:      f.shipped_at?.[0] ? [f.shipped_at[0].format('YYYY-MM-DD'), f.shipped_at[1]?.format('YYYY-MM-DD')] : null,
    created_at:      f.created_at?.[0] ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')] : null,
    only_favorites:  !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:       Array.isArray(f.reference) ? f.reference : [],
    status:          Array.isArray(f.status) ? f.status : [],
    sales_order_id:  Array.isArray(f.sales_order_id) ? f.sales_order_id : [],
    warehouse_id:    Array.isArray(f.warehouse_id) ? f.warehouse_id : [],
    carrier:         f.carrier ?? '',
    tracking_number: f.tracking_number ?? '',
    shipped_at:      f.shipped_at?.[0] ? [dayjs(f.shipped_at[0]), dayjs(f.shipped_at[1])] : null,
    created_at:      f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites:  f.only_favorites ?? false,
});
