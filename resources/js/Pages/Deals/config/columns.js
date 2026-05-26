/**
 * Columnas de la tabla principal de Deals.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve deals de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const dealsTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                      dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                 dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('deals.name'),      dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('companies.singular'), dataIndex: ['company', 'name'], key: 'company', width: 180, mobile: { role: 'meta' } },
    { title: t('deals.pipeline'),  dataIndex: ['pipeline', 'name'], key: 'pipeline', width: 180, mobile: { role: 'meta' } },
    { title: t('deals.stage'),     dataIndex: ['stage', 'name'], key: 'stage', width: 140, mobile: { role: 'meta' } },
    { title: t('deals.value'),     dataIndex: 'value', key: 'value', width: 160, align: 'right', sorter: (a, b) => Number(a.value || 0) - Number(b.value || 0), mobile: { role: 'meta' } },
    { title: t('deals.deal_status'), dataIndex: 'status', key: 'deal_status', width: 120, mobile: { role: 'meta' } },
    { title: t('deals.owner'),     dataIndex: ['owner', 'name'], key: 'owner', width: 160, mobile: { role: 'meta' }, defaultHidden: true },
...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
