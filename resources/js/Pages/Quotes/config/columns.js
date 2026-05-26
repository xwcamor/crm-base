/**
 * Columnas de la tabla principal de Quotes.
 *
 * `isSuper` agrega 2 columnas que admins de workspace no necesitan ver:
 *   - ID
 *   - Workspace (tenant)
 */
export const quotesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                           dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                      dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('quotes.reference'),         dataIndex: 'reference',   key: 'reference',  sorter: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('quotes.company'),           key: 'company',           width: 220, mobile: { role: 'subtitle' } },
    { title: t('quotes.contact'),           key: 'contact',           width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('quotes.deal'),              key: 'deal',              width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'),     dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('quotes.status'),            dataIndex: 'status',      key: 'status',      width: 140, align: 'center', mobile: { role: 'status' } },
    { title: t('quotes.issue_date'),        dataIndex: 'issue_date',  key: 'issue_date',  width: 120, sorter: true, mobile: { role: 'meta' } },
    { title: t('quotes.valid_until'),       dataIndex: 'valid_until', key: 'valid_until', width: 120, sorter: true, mobile: { role: 'meta' } },
    { title: t('quotes.grand_total'),       dataIndex: 'grand_total', key: 'grand_total', width: 150, align: 'right', sorter: true, mobile: { role: 'meta' } },
    { title: t('global.created_at'),        dataIndex: 'created_at',  key: 'created_at',  width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),           key: 'actions',           width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
