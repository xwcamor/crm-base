/**
 * Columnas de la tabla principal de Users.
 *
 * `isSuper` agrega la columna ID y la columna Empresa (tenant) — solo
 * tienen sentido para super (los demás roles ven solo users de su
 * propio tenant, así que la columna Empresa sería ruido redundante). El slug
 * NO se muestra como columna — solo se usa internamente para rutas.
 */
export const usersTableColumns = (t, { isSuper = false } = {}) => {
    const cols = [
        { title: '', dataIndex: 'is_favorite', key: 'favorite', width: 48, alwaysVisible: true, mobile: { role: 'pin' } },
    ];

    if (isSuper) {
        cols.push({ title: 'ID', dataIndex: 'id', key: 'id', sorter: true, width: 80, alwaysVisible: true, mobile: { role: 'meta' } });
    }

    cols.push(
        { title: t('users.photo'), dataIndex: 'photo', key: 'photo', width: 70,  mobile: { role: 'hidden' } },
        { title: t('users.name'),  dataIndex: 'name',  key: 'name',  sorter: true, ellipsis: true, alwaysVisible: true, mobile: { role: 'title' } },
        { title: t('users.email'), dataIndex: 'email', key: 'email', sorter: true, ellipsis: true, mobile: { role: 'subtitle' } },
        { title: t('users.role'),  dataIndex: 'role',  key: 'role',  width: 140, mobile: { role: 'meta' } },
    );

    if (isSuper) {
        cols.push({
            title: t('users.tenant'), dataIndex: ['tenant', 'name'], key: 'tenant',
            width: 160, mobile: { role: 'meta' },
        });
    }

    cols.push(
        { title: t('users.is_active'),   dataIndex: 'is_active',  key: 'status',     sorter: true, width: 120, mobile: { role: 'status' } },
        { title: t('global.created_at'), dataIndex: 'created_at', key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta' }, defaultHidden: true },
        { title: t('global.actions'),    key: 'actions',          width: 100, fixed: 'right', alwaysVisible: true, mobile: { role: 'actions' } },
    );

    return cols;
};
