/**
 * Columnas de la tabla principal de StockTakes.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID
 *   - Workspace (tenant)
 */
export const stockTakesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                            dataIndex: 'is_favorite', key: 'favorite',   width: 48, alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                       dataIndex: 'id',          key: 'id',         width: 80, fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('stock_takes.reference'),     dataIndex: 'reference',   key: 'reference',  sorter: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('stock_takes.warehouse'),     key: 'warehouse',         width: 220, mobile: { role: 'subtitle' } },
    ...(isSuper ? [
        { title: t('tenants.singular'),      dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('stock_takes.status'),        dataIndex: 'status',       key: 'status',       width: 140, align: 'center', mobile: { role: 'status' } },
    { title: t('stock_takes.started_at'),    dataIndex: 'started_at',   key: 'started_at',   width: 160, sorter: true, mobile: { role: 'meta' } },
    { title: t('stock_takes.completed_at'),  dataIndex: 'completed_at', key: 'completed_at', width: 160, sorter: true, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.created_at'),         dataIndex: 'created_at',   key: 'created_at',   width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),            key: 'actions',            width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
