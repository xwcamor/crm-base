/**
 * Columnas de la tabla principal de Payment Methods.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico.
 *   - Workspace (tenant): cruz-tenant.
 */
export const paymentMethodsTableColumns = (t, { isSuper = false } = {}) => [
    { title: 'X',                                       dataIndex: 'is_favorite',         key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                                  dataIndex: 'id',                  key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('payment_methods.name'),                 dataIndex: 'name',                key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('payment_methods.code'),                 dataIndex: 'code',                key: 'code',       width: 140, mobile: { role: 'meta' } },
    { title: t('payment_methods.integration_provider'), dataIndex: 'integration_provider',key: 'provider',   width: 160, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('payment_methods.requires_reference'),   dataIndex: 'requires_reference',  key: 'requires_reference', width: 120, align: 'center', mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('payment_methods.sort_order'),           dataIndex: 'sort_order',          key: 'sort_order', width: 100, align: 'right', mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'),                 dataIndex: ['tenant', 'name'],    key: 'tenant',     width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('payment_methods.is_active'),            dataIndex: 'is_active',           key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),                    dataIndex: 'created_at',          key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),                       key: 'actions',                   width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
