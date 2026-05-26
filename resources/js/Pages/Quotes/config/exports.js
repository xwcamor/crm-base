/**
 * Columnas exportables del modulo Quotes. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const quotesExportableColumns = (t) => [
    { key: 'id',             label: 'ID',                       default: true  },
    { key: 'reference',      label: t('quotes.reference'),      default: true  },
    { key: 'company',        label: t('quotes.company'),        default: true  },
    { key: 'contact',        label: t('quotes.contact'),        default: false },
    { key: 'deal',           label: t('quotes.deal'),           default: false },
    { key: 'status',         label: t('quotes.status'),         default: true  },
    { key: 'issue_date',     label: t('quotes.issue_date'),     default: true  },
    { key: 'valid_until',    label: t('quotes.valid_until'),    default: true  },
    { key: 'currency_code',  label: t('quotes.currency'),       default: false },
    { key: 'subtotal',       label: t('quotes.subtotal'),       default: false },
    { key: 'tax_total',      label: t('quotes.tax_total'),      default: false },
    { key: 'discount_total', label: t('quotes.discount_total'), default: false },
    { key: 'grand_total',    label: t('quotes.grand_total'),    default: true  },
    { key: 'created_at',     label: t('global.created_at'),     default: false },
    { key: 'creator',        label: t('global.created_by'),     default: false },
];

export const quotesExportEndpoints = () => ({
    excel: route('business_management.quotes.export_excel'),
    pdf:   route('business_management.quotes.export_pdf'),
    word:  route('business_management.quotes.export_word'),
    csv:   route('business_management.quotes.export_csv'),
});
