/**
 * Columnas exportables del módulo Deals. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const dealsExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('deals.name'),      default: true  },
{ key: 'is_active',  label: t('deals.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const dealsExportEndpoints = () => ({
    excel: route('crm.deals.export_excel'),
    pdf:   route('crm.deals.export_pdf'),
    word:  route('crm.deals.export_word'),
    csv:   route('crm.deals.export_csv'),
});
