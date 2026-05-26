/**
 * Columnas de la tabla de Trash. Especificas: deleter + deleted_at +
 * deleted_description que no aparecen en la tabla principal.
 */
export const exchangeRatesTrashColumns = (t) => [
    { title: 'ID',                           dataIndex: 'id',                  key: 'id',          width: 80,  mobile: { role: 'meta' } },
    { title: t('exchange_rates.base_code'),  dataIndex: 'base_code',           key: 'base_code',   width: 100, mobile: { role: 'meta' } },
    { title: t('exchange_rates.quote_code'), dataIndex: 'quote_code',          key: 'quote_code',  width: 100, mobile: { role: 'meta' } },
    { title: t('exchange_rates.rate'),       dataIndex: 'rate',                key: 'rate',        width: 130, align: 'right', mobile: { role: 'title' } },
    { title: t('exchange_rates.valid_at'),   dataIndex: 'valid_at',            key: 'valid_at',    width: 180, mobile: { role: 'subtitle' } },
    { title: t('global.deleted_by'),         dataIndex: ['deleter', 'name'],   key: 'deleter',     width: 180, mobile: { role: 'meta' } },
    { title: t('global.deleted_at'),         dataIndex: 'deleted_at',          key: 'deleted_at',  width: 180, mobile: { role: 'meta' } },
    { title: t('global.delete_description'), dataIndex: 'deleted_description', key: 'reason',      ellipsis: true, mobile: { role: 'subtitle' } },
    { title: t('global.actions'),            key: 'actions',                   width: 140, fixed: 'right', mobile: { role: 'actions' } },
];
