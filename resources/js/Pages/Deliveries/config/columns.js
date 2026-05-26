/**
 * Columnas de la tabla principal de Deliveries.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID
 *   - Workspace (tenant)
 */
export const deliveriesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                              dataIndex: 'is_favorite', key: 'favorite',  width: 48, alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                         dataIndex: 'id',          key: 'id',        width: 80, fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('deliveries.reference'),        dataIndex: 'reference',   key: 'reference', sorter: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('deliveries.sales_order'),      key: 'sales_order',       width: 200, mobile: { role: 'subtitle' } },
    { title: t('deliveries.warehouse'),        key: 'warehouse',         width: 220, mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'),        dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('deliveries.status'),           dataIndex: 'status',           key: 'status',           width: 140, align: 'center', mobile: { role: 'status' } },
    { title: t('deliveries.carrier'),          dataIndex: 'carrier',          key: 'carrier',          width: 130, mobile: { role: 'meta' } },
    { title: t('deliveries.tracking_number'),  dataIndex: 'tracking_number',  key: 'tracking_number',  width: 160, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('deliveries.shipped_at'),       dataIndex: 'shipped_at',       key: 'shipped_at',       width: 160, sorter: true, mobile: { role: 'meta' } },
    { title: t('deliveries.delivered_at'),     dataIndex: 'delivered_at',     key: 'delivered_at',     width: 160, sorter: true, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.created_at'),           dataIndex: 'created_at',       key: 'created_at',       width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),              key: 'actions',                width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
