/**
 * Columnas exportables del módulo Companies. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const companiesExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('companies.name'),      default: true  },
{ key: 'is_active',  label: t('companies.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const companiesExportEndpoints = () => ({
    excel: route('crm.companies.export_excel'),
    pdf:   route('crm.companies.export_pdf'),
    word:  route('crm.companies.export_word'),
    csv:   route('crm.companies.export_csv'),
});
