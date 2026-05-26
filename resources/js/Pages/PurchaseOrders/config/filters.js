import dayjs from 'dayjs';

/**
 * Schema de filtros del módulo PurchaseOrders.
 */
export const purchaseOrdersFilterFields = (t, {
    statusOptions = [],
    supplierOptions = [],
    warehouseOptions = [],
} = {}) => [
    { key: 'reference',           label: t('purchase_orders.reference'),    type: 'text' },
    { key: 'status',              label: t('purchase_orders.status'),       type: 'multiselect', options: statusOptions },
    { key: 'supplier_company_id', label: t('purchase_orders.supplier'),     type: 'multiselect', options: supplierOptions },
    { key: 'warehouse_id',        label: t('purchase_orders.warehouse'),    type: 'multiselect', options: warehouseOptions },
    { key: 'order_date',          label: t('purchase_orders.order_date'),   type: 'date_range' },
    { key: 'created_at',          label: t('global.created_at'),            type: 'date_range' },
    { key: 'only_favorites',      label: t('global.only_favorites'),        type: 'switch' },
];

export const purchaseOrdersEmptyFilters = () => ({
    reference: '',
    status: [],
    supplier_company_id: [],
    warehouse_id: [],
    order_date: null,
    created_at: null,
    only_favorites: false,
});

export const hydratePurchaseOrdersFilters = (server) => ({
    reference:           server.reference || '',
    status:              Array.isArray(server.status) ? server.status : (server.status ? [server.status] : []),
    supplier_company_id: Array.isArray(server.supplier_company_id) ? server.supplier_company_id : [],
    warehouse_id:        Array.isArray(server.warehouse_id) ? server.warehouse_id : [],
    order_date: (server.order_date_from && server.order_date_to)
        ? [dayjs(server.order_date_from), dayjs(server.order_date_to)]
        : null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

export const purchaseOrdersFiltersToQuery = (f) => ({
    reference:           f.reference || undefined,
    status:              f.status?.length ? f.status : undefined,
    supplier_company_id: f.supplier_company_id?.length ? f.supplier_company_id : undefined,
    warehouse_id:        f.warehouse_id?.length ? f.warehouse_id : undefined,
    order_date_from:     f.order_date?.[0]?.format('YYYY-MM-DD') ?? undefined,
    order_date_to:       f.order_date?.[1]?.format('YYYY-MM-DD') ?? undefined,
    created_from:        f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:          f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites:      f.only_favorites ? 1 : undefined,
});

export const purchaseOrdersFiltersSummary = (f, t) => {
    const parts = [];
    if (f.reference)                 parts.push(`${t('purchase_orders.reference')}: ${f.reference}`);
    if (f.status?.length)            parts.push(`${t('purchase_orders.status')}: ${f.status.length}`);
    if (f.supplier_company_id?.length) parts.push(`${t('purchase_orders.supplier')}: ${f.supplier_company_id.length}`);
    if (f.warehouse_id?.length)      parts.push(`${t('purchase_orders.warehouse')}: ${f.warehouse_id.length}`);
    if (f.order_date)                parts.push(`${t('purchase_orders.order_date')}: ${f.order_date[0]?.format('YYYY-MM-DD')} → ${f.order_date[1]?.format('YYYY-MM-DD')}`);
    if (f.created_at)                parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

export const serializeSavedFilters = (f) => ({
    reference:           f.reference ?? '',
    status:              f.status ?? [],
    supplier_company_id: f.supplier_company_id ?? [],
    warehouse_id:        f.warehouse_id ?? [],
    order_date:          f.order_date?.[0]
        ? [f.order_date[0].format('YYYY-MM-DD'), f.order_date[1]?.format('YYYY-MM-DD')]
        : null,
    created_at:          f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    reference:           f.reference ?? '',
    status:              Array.isArray(f.status) ? f.status : [],
    supplier_company_id: Array.isArray(f.supplier_company_id) ? f.supplier_company_id : [],
    warehouse_id:        Array.isArray(f.warehouse_id) ? f.warehouse_id : [],
    order_date:          f.order_date?.[0] ? [dayjs(f.order_date[0]), dayjs(f.order_date[1])] : null,
    created_at:          f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites:      f.only_favorites ?? false,
});
