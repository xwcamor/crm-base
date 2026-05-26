/**
 * Columnas exportables. Independientes de las visibles en pantalla — el usuario
 * elige en el ExportDialog que columnas exportar.
 */
export const exchangeRatesExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                            default: true  },
    { key: 'base_code',  label: t('exchange_rates.base_code'),   default: true  },
    { key: 'quote_code', label: t('exchange_rates.quote_code'),  default: true  },
    { key: 'rate',       label: t('exchange_rates.rate'),        default: true  },
    { key: 'valid_at',   label: t('exchange_rates.valid_at'),    default: true  },
    { key: 'source',     label: t('exchange_rates.source'),      default: true  },
    { key: 'is_active',  label: t('exchange_rates.is_active'),   default: true  },
    { key: 'created_at', label: t('global.created_at'),          default: false },
    { key: 'creator',    label: t('global.created_by'),          default: false },
];

export const exchangeRatesExportEndpoints = () => ({
    excel: route('business_management.exchange_rates.export_excel'),
    pdf:   route('business_management.exchange_rates.export_pdf'),
    word:  route('business_management.exchange_rates.export_word'),
    csv:   route('business_management.exchange_rates.export_csv'),
});
