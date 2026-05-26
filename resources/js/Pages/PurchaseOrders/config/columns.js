/**
 * Columnas de la tabla principal de PurchaseOrders.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID (tecnico) y Workspace (tenant).
 */
export const purchaseOrdersTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                                  dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                             dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('purchase_orders.reference'),       dataIndex: 'reference',          key: 'reference', sorter: (a, b) => (a.reference || '').localeCompare(b.reference || ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('purchase_orders.supplier'),        dataIndex: ['supplier', 'name'], key: 'supplier',  width: 200, mobile: { role: 'subtitle' } },
    { title: t('purchase_orders.warehouse'),       dataIndex: ['warehouse', 'name'], key: 'warehouse', width: 180, mobile: { role: 'meta' } },
    ...(isSuper ? [
        { title: t('tenants.singular'),            dataIndex: ['tenant', 'name'],   key: 'tenant',    width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('purchase_orders.status'),          dataIndex: 'status',             key: 'status',    width: 150, align: 'center', mobile: { role: 'status' } },
    { title: t('purchase_orders.order_date'),      dataIndex: 'order_date',         key: 'order_date', width: 120, mobile: { role: 'meta' } },
    { title: t('purchase_orders.expected_delivery_date'), dataIndex: 'expected_delivery_date', key: 'eta', width: 140, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('purchase_orders.grand_total'),     dataIndex: 'grand_total',        key: 'grand_total', width: 140, align: 'right', mobile: { role: 'meta' } },
    { title: t('global.created_at'),               dataIndex: 'created_at',         key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),                  key: 'actions',                  width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
