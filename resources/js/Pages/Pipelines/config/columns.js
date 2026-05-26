/**
 * Columnas de la tabla principal de Pipelines.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve pipelines de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const pipelinesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                      dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                 dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('pipelines.name'),      dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('pipelines.color'),     dataIndex: 'color',       key: 'color',      width: 80,  align: 'center', mobile: { role: 'meta' } },
    { title: t('pipelines.stages_count'), dataIndex: 'stages_count', key: 'stages_count', width: 100, align: 'center', mobile: { role: 'meta' } },
    { title: t('pipelines.open_deals_count'), dataIndex: 'open_deals_count', key: 'open_deals_count', width: 130, align: 'center', mobile: { role: 'meta' } },
...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('pipelines.is_active'), dataIndex: 'is_active',   key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
