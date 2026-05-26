/**
 * Columnas de la tabla principal de Companies.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve companies de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const companiesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                      dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                 dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('companies.name'),      dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('companies.industry'),  dataIndex: ['industry', 'name'], key: 'industry', width: 160, mobile: { role: 'meta' } },
    { title: t('companies.lifecycle_stage'), dataIndex: 'lifecycle_stage', key: 'lifecycle', width: 130, mobile: { role: 'meta' } },
    { title: t('companies.country'),   dataIndex: ['country', 'name'], key: 'country', width: 140, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('companies.owner'),     dataIndex: ['owner', 'name'], key: 'owner', width: 160, mobile: { role: 'meta' } },
    { title: t('deals.plural'),        dataIndex: 'deals_count', key: 'deals_count', width: 100, align: 'center', mobile: { role: 'meta' } },
...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('companies.is_active'), dataIndex: 'is_active',   key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
