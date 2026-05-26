/**
 * Columnas de la tabla principal de Invoices.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID: identificador tecnico (ruido para admins, util para super).
 *   - Workspace (tenant): cross-tenant, super ve invoices de varios
 *     workspaces. Admin solo ve los suyos, la columna seria redundante.
 *
 * El identificador del dominio es `number` (numero correlativo oficial).
 * `balance_due` es la metrica clave para tracking de AR — siempre visible.
 */
export const invoicesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                       dataIndex: 'is_favorite', key: 'favorite',     width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                  dataIndex: 'id',          key: 'id',           width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('invoices.number'),      dataIndex: 'number',      key: 'number',       width: 160, sorter: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('invoices.company'),     dataIndex: ['company', 'name'], key: 'company', mobile: { role: 'subtitle' } },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('invoices.status'),      dataIndex: 'status',      key: 'status',       width: 120, align: 'center', sorter: true, mobile: { role: 'status' } },
    { title: t('invoices.issue_date'),  dataIndex: 'issue_date',  key: 'issue_date',   width: 120, sorter: true, mobile: { role: 'meta' } },
    { title: t('invoices.due_date'),    dataIndex: 'due_date',    key: 'due_date',     width: 120, sorter: true, mobile: { role: 'meta' } },
    { title: t('invoices.grand_total'), dataIndex: 'grand_total', key: 'grand_total',  width: 140, align: 'right', sorter: true, mobile: { role: 'meta' } },
    { title: t('invoices.amount_paid'), dataIndex: 'amount_paid', key: 'amount_paid',  width: 130, align: 'right', sorter: true, defaultHidden: true, mobile: { role: 'meta' } },
    { title: t('invoices.balance_due'), dataIndex: 'balance_due', key: 'balance_due',  width: 140, align: 'right', sorter: true, alwaysVisible: true, mobile: { role: 'status' } },
    { title: t('global.created_at'),    dataIndex: 'created_at',  key: 'created_at',   width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),       key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
