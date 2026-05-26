/**
 * Columnas exportables del módulo Customers. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const customersExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('customers.name'),      default: true  },
    // @scaffold:remove-begin commercial-exportable
    { key: 'cod',        label: t('customers.cod'),       default: true  },
    { key: 'country',    label: t('customers.country'),   default: true  },
    // @scaffold:remove-end
    { key: 'is_active',  label: t('customers.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const customersExportEndpoints = () => ({
    excel: route('business_management.customers.export_excel'),
    pdf:   route('business_management.customers.export_pdf'),
    word:  route('business_management.customers.export_word'),
    csv:   route('business_management.customers.export_csv'),
});
