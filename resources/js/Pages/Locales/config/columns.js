/**
 * Columnas de la tabla principal de Locales.
 */
export const localesTableColumns = (t) => [
    { title: '',                    dataIndex: 'is_favorite', key: 'favorite',  width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    { title: t('locales.id'),        dataIndex: 'id',          key: 'id',         sorter: true, width: 80,  alwaysVisible: true, mobile: { role: 'hidden' } },
    { title: t('locales.name'),      dataIndex: 'name',        key: 'name',       sorter: true, ellipsis: true, alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('locales.code'),      dataIndex: 'code',        key: 'code',       sorter: true, width: 110, mobile: { role: 'meta' } },
    { title: t('locales.language'),  dataIndex: 'language',    key: 'language',   sorter: true, width: 180, mobile: { role: 'meta' } },
    { title: t('locales.is_active'), dataIndex: 'is_active',   key: 'status',     sorter: true, width: 130, mobile: { role: 'status' } },
    { title: t('global.created_at'), dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),    key: 'actions',           width: 100, fixed: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
