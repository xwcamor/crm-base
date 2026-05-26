/**
 * Columnas de la tabla principal de Messages.
 */
export const messagesTableColumns = (t) => [
    { title: '',                       dataIndex: 'is_favorite',  key: 'favorite',    width: 48,  alwaysVisible: true, mobile: { role: 'pin' } },
    { title: 'ID',                      dataIndex: 'id',           key: 'id',          width: 80,  fixed: 'left', alwaysVisible: true, sorter: (a, b) => a.id - b.id, mobile: { role: 'meta' } },
    { title: t('messages.subject'),     dataIndex: 'subject',      key: 'subject',     sorter: (a, b) => (a.subject ?? '').localeCompare(b.subject ?? ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('messages.audience_type'),dataIndex: 'audience_type',key: 'audience',   width: 160, mobile: { role: 'meta' } },
    { title: t('messages.recipients_count'), dataIndex: 'recipients_count', key: 'recipients', width: 130, align: 'right', mobile: { role: 'meta' } },
    { title: t('messages.read_count'),  dataIndex: 'read_count',   key: 'read',        width: 130, align: 'right', mobile: { role: 'meta' } },
    { title: t('messages.replies_count'), dataIndex: 'replies_count', key: 'replies',  width: 120, align: 'right', mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('messages.published_at'),dataIndex: 'published_at', key: 'published_at',width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('messages.expires_at'),  dataIndex: 'expires_at',   key: 'expires_at',  width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('messages.is_active'),   dataIndex: 'is_active',    key: 'status',      width: 110, align: 'center', mobile: { role: 'status' } },
    { title: t('global.created_at'),    dataIndex: 'created_at',   key: 'created_at',  width: 180, mobile: { role: 'meta' }, defaultHidden: true },
    { title: t('global.actions'),       key: 'actions',            width: 200, fixed: 'right', align: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
];
