/**
 * Columnas exportables del modulo SalesOrders. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const salesOrdersExportableColumns = (t) => [
    { key: 'id',                     label: 'ID',                                  default: true  },
    { key: 'reference',              label: t('sales_orders.reference'),           default: true  },
    { key: 'company',                label: t('sales_orders.company'),             default: true  },
    { key: 'warehouse',              label: t('sales_orders.warehouse'),           default: true  },
    { key: 'status',                 label: t('sales_orders.status'),              default: true  },
    { key: 'payment_status',         label: t('sales_orders.payment_status'),      default: true  },
    { key: 'order_date',             label: t('sales_orders.order_date'),          default: true  },
    { key: 'expected_delivery_date', label: t('sales_orders.expected_delivery_date'), default: false },
    { key: 'currency_code',          label: t('sales_orders.currency'),            default: false },
    { key: 'subtotal',               label: t('sales_orders.subtotal'),            default: false },
    { key: 'tax_total',              label: t('sales_orders.tax_total'),           default: false },
    { key: 'grand_total',            label: t('sales_orders.grand_total'),         default: true  },
    { key: 'created_at',             label: t('global.created_at'),                default: false },
    { key: 'creator',                label: t('global.created_by'),                default: false },
];

export const salesOrdersExportEndpoints = () => ({
    excel: route('business_management.sales_orders.export_excel'),
    pdf:   route('business_management.sales_orders.export_pdf'),
    word:  route('business_management.sales_orders.export_word'),
    csv:   route('business_management.sales_orders.export_csv'),
});
