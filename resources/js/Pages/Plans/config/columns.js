/**
 * Columnas de la tabla principal de Plans (super only).
 * No hay split por isSuper (todo el modulo es super) — la columna `tenant`
 * no aplica porque Plans es catalogo global, no per-tenant.
 */
export const plansTableColumns = (t) => [
    { title: 'ID',                       dataIndex: 'id',                     key: 'id',          width: 70,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    { title: t('plans.slug'),            dataIndex: 'slug',                   key: 'slug',        width: 130, sorter: (a, b) => (a.slug ?? '').localeCompare(b.slug ?? ''), alwaysVisible: true, mobile: { role: 'meta' } },
    { title: t('plans.name'),            dataIndex: 'name',                   key: 'name',        sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('plans.tagline'),         dataIndex: 'tagline',                key: 'tagline',     ellipsis: true, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('plans.support_level'),   dataIndex: 'support_level',          key: 'support',     width: 130, mobile: { role: 'meta' } },
    { title: t('plans.max_users'),       dataIndex: 'max_users',              key: 'users',       width: 100, align: 'center', sorter: (a, b) => a.max_users - b.max_users, mobile: { role: 'meta' } },
    { title: t('plans.max_records_per_module'), dataIndex: 'max_records_per_module', key: 'records', width: 120, align: 'center', sorter: (a, b) => a.max_records_per_module - b.max_records_per_module, mobile: { role: 'meta' } },
    { title: t('plans.price_monthly'),   dataIndex: 'price_monthly',          key: 'monthly',     width: 110, align: 'right', sorter: (a, b) => a.price_monthly - b.price_monthly, mobile: { role: 'meta' } },
    { title: t('plans.price_yearly'),    dataIndex: 'price_yearly',           key: 'yearly',      width: 110, align: 'right', sorter: (a, b) => a.price_yearly - b.price_yearly, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('plans.tab_tenants'),     dataIndex: 'tenants_count',          key: 'tenants',     width: 100, align: 'center', sorter: (a, b) => a.tenants_count - b.tenants_count, mobile: { role: 'meta' } },
    { title: t('plans.is_active'),       dataIndex: 'is_active',              key: 'status',      width: 100, align: 'center', mobile: { role: 'status' } },
    { title: t('plans.is_public'),       dataIndex: 'is_public',              key: 'public',      width: 100, align: 'center', mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.created_at'),     dataIndex: 'created_at',             key: 'created_at',  width: 170, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),        key: 'actions',                      width: 180, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
