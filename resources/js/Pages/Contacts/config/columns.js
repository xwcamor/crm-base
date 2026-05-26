/**
 * Columnas de la tabla principal de Contacts.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve contacts de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const contactsTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                      dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                 dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('contacts.name'),      dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('contacts.email'),     dataIndex: 'primary_email', key: 'email',   width: 220, mobile: { role: 'meta' } },
    { title: t('contacts.phone'),     dataIndex: 'primary_phone', key: 'phone',   width: 140, mobile: { role: 'meta' } },
    { title: t('contacts.position'),  dataIndex: 'job_title',    key: 'position', width: 160, mobile: { role: 'meta' } },
    { title: t('companies.singular'), dataIndex: ['company', 'name'], key: 'company', width: 180, mobile: { role: 'meta' } },
    { title: t('contacts.lifecycle_stage'), dataIndex: 'lifecycle_stage', key: 'lifecycle', width: 130, mobile: { role: 'meta' } },
    { title: t('contacts.owner'),     dataIndex: ['owner', 'name'], key: 'owner', width: 160, mobile: { role: 'meta' }, defaultHidden: true },
...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('contacts.is_active'), dataIndex: 'is_active',   key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
