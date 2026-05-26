/**
 * Columnas exportables del modulo Deliveries. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const deliveriesExportableColumns = (t) => [
    { key: 'id',              label: 'ID',                              default: true  },
    { key: 'reference',       label: t('deliveries.reference'),         default: true  },
    { key: 'sales_order',     label: t('deliveries.sales_order'),       default: true  },
    { key: 'warehouse',       label: t('deliveries.warehouse'),         default: true  },
    { key: 'status',          label: t('deliveries.status'),            default: true  },
    { key: 'carrier',         label: t('deliveries.carrier'),           default: true  },
    { key: 'tracking_number', label: t('deliveries.tracking_number'),   default: true  },
    { key: 'shipping_method', label: t('deliveries.shipping_method'),   default: false },
    { key: 'shipping_cost',   label: t('deliveries.shipping_cost'),     default: false },
    { key: 'shipped_at',      label: t('deliveries.shipped_at'),        default: true  },
    { key: 'delivered_at',    label: t('deliveries.delivered_at'),      default: false },
    { key: 'signed_by_name',  label: t('deliveries.signed_by_name'),    default: false },
    { key: 'notes',           label: t('deliveries.notes'),             default: false },
    { key: 'created_at',      label: t('global.created_at'),            default: false },
    { key: 'creator',         label: t('global.created_by'),            default: false },
];

export const deliveriesExportEndpoints = () => ({
    excel: route('business_management.deliveries.export_excel'),
    pdf:   route('business_management.deliveries.export_pdf'),
    word:  route('business_management.deliveries.export_word'),
    csv:   route('business_management.deliveries.export_csv'),
});
