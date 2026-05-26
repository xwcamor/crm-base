/**
 * Columnas de la tabla principal de SalesOrders.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID
 *   - Workspace (tenant)
 */
export const salesOrdersTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                              dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                         dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('sales_orders.reference'),      dataIndex: 'reference',   key: 'reference',  sorter: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('sales_orders.company'),        key: 'company',           width: 220, mobile: { role: 'subtitle' } },
    { title: t('sales_orders.warehouse'),      key: 'warehouse',         width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'),        dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('sales_orders.status'),         dataIndex: 'status',          key: 'status',         width: 140, align: 'center', mobile: { role: 'status' } },
    { title: t('sales_orders.payment_status'), dataIndex: 'payment_status',  key: 'payment_status', width: 130, align: 'center', mobile: { role: 'status' } },
    { title: t('sales_orders.order_date'),     dataIndex: 'order_date',      key: 'order_date',     width: 120, sorter: true, mobile: { role: 'meta' } },
    { title: t('sales_orders.grand_total'),    dataIndex: 'grand_total',     key: 'grand_total',    width: 150, align: 'right', sorter: true, mobile: { role: 'meta' } },
    { title: t('global.created_at'),           dataIndex: 'created_at',      key: 'created_at',     width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),              key: 'actions',               width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
