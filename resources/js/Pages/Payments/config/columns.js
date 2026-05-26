/**
 * Columnas de la tabla principal de Payments.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cruz-tenant, super ve payments de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 */
export const paymentsTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                       dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                  dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('payments.reference'),   dataIndex: 'reference',   key: 'reference',  width: 160, sorter: (a, b) => (a.reference || '').localeCompare(b.reference || ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('payments.paid_at'),     dataIndex: 'paid_at',     key: 'paid_at',    width: 170, sorter: (a, b) => new Date(a.paid_at) - new Date(b.paid_at), mobile: { role: 'subtitle' } },
    { title: t('payments.company'),     key: 'company',           width: 200, mobile: { role: 'meta' } },
    { title: t('payments.invoice_number'), key: 'invoice',        width: 140, mobile: { role: 'meta' } },
    { title: t('payments.payment_method'), key: 'method',         width: 160, mobile: { role: 'meta' } },
    { title: t('payments.type'),        dataIndex: 'type',        key: 'type',       width: 150, mobile: { role: 'meta' } },
    { title: t('payments.amount'),      dataIndex: 'amount',      key: 'amount',     width: 150, align: 'right', sorter: (a, b) => Number(a.amount) - Number(b.amount), mobile: { role: 'meta' } },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('payments.status'),      dataIndex: 'status',      key: 'status',     width: 130, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),    dataIndex: 'created_at',  key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),       key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
