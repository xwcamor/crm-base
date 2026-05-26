/**
 * Columnas exportables del módulo TaxClasses. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const tax_classesExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('tax_classes.name'),      default: true  },
{ key: 'is_active',  label: t('tax_classes.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const tax_classesExportEndpoints = () => ({
    excel: route('business_management.tax_classes.export_excel'),
    pdf:   route('business_management.tax_classes.export_pdf'),
    word:  route('business_management.tax_classes.export_word'),
    csv:   route('business_management.tax_classes.export_csv'),
});
