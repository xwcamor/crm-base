/**
 * Columnas exportables del modulo Invoices. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const invoicesExportableColumns = (t) => [
    { key: 'id',             label: 'ID',                        default: false },
    { key: 'number',         label: t('invoices.number'),        default: true  },
    { key: 'reference',      label: t('invoices.reference'),     default: false },
    { key: 'company',        label: t('invoices.company'),       default: true  },
    { key: 'contact',        label: t('invoices.contact'),       default: false },
    { key: 'status',         label: t('invoices.status'),        default: true  },
    { key: 'issue_date',     label: t('invoices.issue_date'),    default: true  },
    { key: 'due_date',       label: t('invoices.due_date'),      default: true  },
    { key: 'currency_code',  label: t('invoices.currency'),      default: false },
    { key: 'subtotal',       label: t('invoices.subtotal'),      default: false },
    { key: 'tax_total',      label: t('invoices.tax_total'),     default: false },
    { key: 'grand_total',    label: t('invoices.grand_total'),   default: true  },
    { key: 'amount_paid',    label: t('invoices.amount_paid'),   default: true  },
    { key: 'balance_due',    label: t('invoices.balance_due'),   default: true  },
    { key: 'created_at',     label: t('global.created_at'),      default: false },
    { key: 'creator',        label: t('global.created_by'),      default: false },
];

export const invoicesExportEndpoints = () => ({
    excel: route('business_management.invoices.export_excel'),
    pdf:   route('business_management.invoices.export_pdf'),
    word:  route('business_management.invoices.export_word'),
    csv:   route('business_management.invoices.export_csv'),
});
