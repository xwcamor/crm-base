import dayjs from 'dayjs';

/**
 * Schema de filtros del modulo SalesOrders. Acepta listas de opciones para
 * los multiselect (companies, warehouses, statuses, payment_statuses).
 */
export const salesOrdersFilterFields = (t, {
    companyOptions = [], warehouseOptions = [],
    statusOptions = [], paymentStatusOptions = [],
} = {}) => [
    { key: 'reference',      label: t('sales_orders.reference'),       type: 'tags' },
    { key: 'status',         label: t('sales_orders.status'),          type: 'multiselect', options: statusOptions },
    { key: 'payment_status', label: t('sales_orders.payment_status'),  type: 'multiselect', options: paymentStatusOptions },
    { key: 'company_id',     label: t('sales_orders.company'),         type: 'multiselect', options: companyOptions },
    { key: 'warehouse_id',   label: t('sales_orders.warehouse'),       type: 'multiselect', options: warehouseOptions },
    { key: 'order_at',       label: t('sales_orders.order_date'),      type: 'date_range' },
    { key: 'created_at',     label: t('global.created_at'),            type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'),        type: 'switch' },
];

export const salesOrdersEmptyFilters = () => ({
    reference: [],
    status: [],
    payment_status: [],
    company_id: [],
    warehouse_id: [],
    order_at: null,
    created_at: null,
    only_favorites: false,
});

export const hydrateSalesOrdersFilters = (server) => ({
    reference:      Array.isArray(server.reference) ? server.reference : (server.reference ? [server.reference] : []),
    status:         Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    payment_status: Array.isArray(server.payment_status) ? server.payment_status : (server.payment_status ? [server.payment_status] : []),
    company_id:     Array.isArray(server.company_id) ? server.company_id : (server.company_id ? [server.company_id] : []),
    warehouse_id:   Array.isArray(server.warehouse_id) ? server.warehouse_id : (server.warehouse_id ? [server.warehouse_id] : []),
    order_at:       (server.order_from && server.order_to) ? [dayjs(server.order_from), dayjs(server.order_to)] : null,
    created_at:     (server.created_from && server.created_to) ? [dayjs(server.created_from), dayjs(server.created_to)] : null,
    only_favorites: server.only_favorites ?? false,
});

export const salesOrdersFiltersToQuery = (f) => ({
    reference:      f.reference?.length ? f.reference : undefined,
    status:         f.status?.length ? f.status : undefined,
    payment_status: f.payment_status?.length ? f.payment_status : undefined,
    company_id:     f.company_id?.length ? f.company_id : undefined,
    warehouse_id:   f.warehouse_id?.length ? f.warehouse_id : undefined,
    order_from:     f.order_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    order_to:       f.order_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

export const salesOrdersFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference?.length)      parts.push(`${t('sales_orders.reference')}: ${f.reference.join(', ')}`);
    if (f.status?.length)         parts.push(`${t('sales_orders.status')}: ${f.status.length}`);
    if (f.payment_status?.length) parts.push(`${t('sales_orders.payment_status')}: ${f.payment_status.length}`);
    if (f.company_id?.length)     parts.push(`${t('sales_orders.company')}: ${f.company_id.length}`);
    if (f.warehouse_id?.length)   parts.push(`${t('sales_orders.warehouse')}: ${f.warehouse_id.length}`);
    if (f.order_at)               parts.push(`${t('sales_orders.order_date')}: ${f.order_at[0]?.format('YYYY-MM-DD')} -> ${f.order_at[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at)             parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} -> ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' . ');
};

export const serializeSavedFilters = (f) => ({
    reference:      f.reference ?? [],
    status:         f.status ?? [],
    payment_status: f.payment_status ?? [],
    company_id:     f.company_id ?? [],
    warehouse_id:   f.warehouse_id ?? [],
    order_at:       f.order_at?.[0] ? [f.order_at[0].format('YYYY-MM-DD'), f.order_at[1]?.format('YYYY-MM-DD')] : null,
    created_at:     f.created_at?.[0] ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')] : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:      Array.isArray(f.reference) ? f.reference : [],
    status:         Array.isArray(f.status) ? f.status : [],
    payment_status: Array.isArray(f.payment_status) ? f.payment_status : [],
    company_id:     Array.isArray(f.company_id) ? f.company_id : [],
    warehouse_id:   Array.isArray(f.warehouse_id) ? f.warehouse_id : [],
    order_at:       f.order_at?.[0] ? [dayjs(f.order_at[0]), dayjs(f.order_at[1])] : null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
