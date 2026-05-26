/**
 * Columnas de la tabla principal de ExchangeRates.
 *
 * `isSuper` agrega ID + Workspace columns para super admins.
 */
export const exchangeRatesTableColumns = (t, { isSuper = false } = {}) => [
    { title: '',                          dataIndex: 'is_favorite', key: 'favorite',   width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    ...(isSuper ? [
        { title: 'ID',                     dataIndex: 'id',          key: 'id',         width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    ] : []),
    { title: t('exchange_rates.base_code'),  dataIndex: 'base_code',  key: 'base_code',  width: 100, sorter: (a, b) => (a.base_code ?? '').localeCompare(b.base_code ?? ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('exchange_rates.quote_code'), dataIndex: 'quote_code', key: 'quote_code', width: 100, sorter: (a, b) => (a.quote_code ?? '').localeCompare(b.quote_code ?? ''), alwaysVisible: true, mobile: { role: 'meta' } },
    { title: t('exchange_rates.rate'),       dataIndex: 'rate',       key: 'rate',       width: 140, align: 'right', sorter: (a, b) => Number(a.rate) - Number(b.rate), mobile: { role: 'meta' } },
    { title: t('exchange_rates.valid_at'),   dataIndex: 'valid_at',   key: 'valid_at',   width: 180, sorter: true, mobile: { role: 'meta' } },
    { title: t('exchange_rates.source'),     dataIndex: 'source',     key: 'source',     width: 140, mobile: { role: 'meta' }, defaultHidden: false },
    ...(isSuper ? [
        { title: t('tenants.singular'),       dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, mobile: { role: 'meta' } },
    ] : []),
    { title: t('exchange_rates.is_active'),  dataIndex: 'is_active',  key: 'status',     width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),         dataIndex: 'created_at', key: 'created_at', width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),            key: 'actions',          width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
