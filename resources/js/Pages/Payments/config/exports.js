/**
 * Columnas exportables del modulo Payments. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const paymentsExportableColumns = (t) => [
    { key: 'id',            label: 'ID',                          default: true  },
    { key: 'reference',     label: t('payments.reference'),       default: true  },
    { key: 'paid_at',       label: t('payments.paid_at'),         default: true  },
    { key: 'company',       label: t('payments.company'),         default: true  },
    { key: 'invoice_number',label: t('payments.invoice_number'),  default: true  },
    { key: 'type',          label: t('payments.type'),            default: true  },
    { key: 'payment_method',label: t('payments.payment_method'),  default: true  },
    { key: 'amount',        label: t('payments.amount'),          default: true  },
    { key: 'currency_code', label: t('payments.currency'),        default: true  },
    { key: 'status',        label: t('payments.status'),          default: true  },
    { key: 'bank_reference',label: t('payments.bank_reference'),  default: false },
    { key: 'notes',         label: t('payments.notes'),           default: false },
    { key: 'created_at',    label: t('global.created_at'),        default: false },
    { key: 'creator',       label: t('global.created_by'),        default: false },
];

export const paymentsExportEndpoints = () => ({
    excel: route('business_management.payments.export_excel'),
    pdf:   route('business_management.payments.export_pdf'),
    word:  route('business_management.payments.export_word'),
    csv:   route('business_management.payments.export_csv'),
});
