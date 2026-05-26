/**
 * Columnas de la tabla principal de Products.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve products de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const productsTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                       dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                  dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('products.sku'),         dataIndex: 'sku',         key: 'sku',        width: 140, sorter: (a, b) => (a.sku ?? '').localeCompare(b.sku ?? ''), mobile: { role: 'meta' } },
    { title: t('products.name'),        dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('products.type'),        dataIndex: 'type',        key: 'type',       width: 140, mobile: { role: 'meta' } },
    { title: t('products.category'),    dataIndex: ['category', 'name'], key: 'category', width: 160, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('products.brand'),       dataIndex: 'brand',       key: 'brand',      width: 130, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('products.list_price'),  dataIndex: 'list_price',  key: 'list_price', width: 140, align: 'right', sorter: (a, b) => Number(a.list_price) - Number(b.list_price), mobile: { role: 'meta' } },
    { title: t('products.currency'),    dataIndex: 'currency_code', key: 'currency_code', width: 90, mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('products.is_active'),   dataIndex: 'is_active',   key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),    dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),       key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
