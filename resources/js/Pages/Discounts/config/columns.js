/**
 * Columnas de la tabla principal de Discounts.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve discounts de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const discountsTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                     dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('discounts.code'),     dataIndex: 'code',        key: 'code',       width: 150, sorter: (a, b) => (a.code ?? '').localeCompare(b.code ?? ''), alwaysVisible: true, mobile: { role: 'meta' } },
    { title: t('discounts.name'),     dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('discounts.type'),     dataIndex: 'type',        key: 'type',       width: 140, mobile: { role: 'meta' } },
    { title: t('discounts.value'),    dataIndex: 'value',       key: 'value',      width: 120, align: 'right', mobile: { role: 'meta' } },
    { title: t('discounts.usage_count'), dataIndex: 'usage_count', key: 'usage',   width: 110, align: 'right', mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('discounts.valid_until'),  dataIndex: 'valid_until', key: 'valid_until', width: 170, mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('discounts.is_active'), dataIndex: 'is_active',   key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
