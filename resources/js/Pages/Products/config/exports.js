/**
 * Columnas exportables del módulo Products. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const productsExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('products.name'),      default: true  },
{ key: 'is_active',  label: t('products.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const productsExportEndpoints = () => ({
    excel: route('business_management.products.export_excel'),
    pdf:   route('business_management.products.export_pdf'),
    word:  route('business_management.products.export_word'),
    csv:   route('business_management.products.export_csv'),
});
