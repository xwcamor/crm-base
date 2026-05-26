/**
 * Columnas exportables del módulo Users. Independientes de las visibles en
 * pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const usersExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                   default: true  },
    { key: 'name',       label: t('users.name'),        default: true  },
    { key: 'email',      label: t('users.email'),       default: true  },
    { key: 'role',       label: t('users.role'),        default: true  },
    { key: 'is_active',  label: t('users.is_active'),   default: true  },
    { key: 'tenant',     label: t('users.tenant'),      default: false },
    { key: 'created_at', label: t('global.created_at'), default: false },
];

export const usersExportEndpoints = () => ({
    excel: route('user_management.users.export_excel'),
    pdf:   route('user_management.users.export_pdf'),
    word:  route('user_management.users.export_word'),
    csv:   route('user_management.users.export_csv'),
});
