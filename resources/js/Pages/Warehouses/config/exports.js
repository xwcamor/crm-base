/**
 * Columnas exportables del módulo Warehouses. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const warehousesExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('warehouses.name'),      default: true  },
{ key: 'is_active',  label: t('warehouses.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const warehousesExportEndpoints = () => ({
    excel: route('business_management.warehouses.export_excel'),
    pdf:   route('business_management.warehouses.export_pdf'),
    word:  route('business_management.warehouses.export_word'),
    csv:   route('business_management.warehouses.export_csv'),
});
