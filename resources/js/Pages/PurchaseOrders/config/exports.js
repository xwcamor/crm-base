/**
 * Columnas exportables del módulo PurchaseOrders.
 */
export const purchaseOrdersExportableColumns = (t) => [
    { key: 'id',                     label: 'ID',                                            default: true  },
    { key: 'reference',              label: t('purchase_orders.reference'),                  default: true  },
    { key: 'supplier',               label: t('purchase_orders.supplier'),                   default: true  },
    { key: 'warehouse',              label: t('purchase_orders.warehouse'),                  default: true  },
    { key: 'status',                 label: t('purchase_orders.status'),                     default: true  },
    { key: 'order_date',             label: t('purchase_orders.order_date'),                 default: true  },
    { key: 'expected_delivery_date', label: t('purchase_orders.expected_delivery_date'),     default: false },
    { key: 'currency_code',          label: t('purchase_orders.currency'),                   default: false },
    { key: 'subtotal',               label: t('purchase_orders.subtotal'),                   default: false },
    { key: 'tax_total',              label: t('purchase_orders.tax_total'),                  default: false },
    { key: 'grand_total',            label: t('purchase_orders.grand_total'),                default: true  },
    { key: 'owner',                  label: t('purchase_orders.owner'),                      default: false },
    { key: 'created_at',             label: t('global.created_at'),                          default: true  },
    { key: 'creator',                label: t('global.created_by'),                          default: false },
];

export const purchaseOrdersExportEndpoints = () => ({
    excel: route('business_management.purchase_orders.export_excel'),
    pdf:   route('business_management.purchase_orders.export_pdf'),
    word:  route('business_management.purchase_orders.export_word'),
    csv:   route('business_management.purchase_orders.export_csv'),
});
