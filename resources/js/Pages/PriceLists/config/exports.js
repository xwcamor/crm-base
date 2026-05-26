/**
 * Columnas exportables del modulo PriceLists. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const priceListsExportableColumns = (t) => [
    { key: 'id',                  label: 'ID',                                  default: true  },
    { key: 'name',                label: t('price_lists.name'),                 default: true  },
    { key: 'description',         label: t('price_lists.description'),          default: false },
    { key: 'currency_code',       label: t('price_lists.currency'),             default: true  },
    { key: 'global_discount_pct', label: t('price_lists.global_discount_pct'),  default: true  },
    { key: 'priority',            label: t('price_lists.priority'),             default: true  },
    { key: 'valid_from',          label: t('price_lists.valid_from'),           default: false },
    { key: 'valid_until',         label: t('price_lists.valid_until'),          default: true  },
    { key: 'is_default',          label: t('price_lists.is_default'),           default: true  },
    { key: 'is_active',           label: t('price_lists.is_active'),            default: true  },
    { key: 'created_at',          label: t('global.created_at'),                default: true  },
    { key: 'creator',             label: t('global.created_by'),                default: false },
];

export const priceListsExportEndpoints = () => ({
    excel: route('business_management.price_lists.export_excel'),
    pdf:   route('business_management.price_lists.export_pdf'),
    word:  route('business_management.price_lists.export_word'),
    csv:   route('business_management.price_lists.export_csv'),
});
