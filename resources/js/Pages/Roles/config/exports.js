/**
 * Columnas exportables del módulo Roles. Independientes de las visibles en
 * pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const rolesExportableColumns = (t) => [
    { key: 'id',                label: 'ID',                         default: true  },
    { key: 'name',              label: t('roles.name'),              default: true  },
    { key: 'description',       label: t('roles.description'),       default: true  },
    { key: 'is_active',         label: t('roles.is_active'),         default: true  },
    { key: 'permissions_count', label: t('roles.permissions_count'), default: true  },
    { key: 'users_count',       label: t('roles.users_count'),       default: true  },
    { key: 'created_at',        label: t('global.created_at'),       default: false },
];

export const rolesExportEndpoints = () => ({
    excel: route('user_management.roles.export_excel'),
    pdf:   route('user_management.roles.export_pdf'),
    word:  route('user_management.roles.export_word'),
    csv:   route('user_management.roles.export_csv'),
});
