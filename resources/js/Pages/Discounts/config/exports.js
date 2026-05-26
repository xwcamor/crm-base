/**
 * Columnas exportables del módulo Discounts. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const discountsExportableColumns = (t) => [
    { key: 'id',                  label: 'ID',                              default: true  },
    { key: 'code',                label: t('discounts.code'),               default: true  },
    { key: 'name',                label: t('discounts.name'),               default: true  },
    { key: 'description',         label: t('discounts.description'),        default: false },
    { key: 'type',                label: t('discounts.type'),               default: true  },
    { key: 'value',               label: t('discounts.value'),              default: true  },
    { key: 'currency_code',       label: t('discounts.currency'),           default: false },
    { key: 'min_purchase_amount', label: t('discounts.min_purchase_amount'),default: false },
    { key: 'usage_limit',         label: t('discounts.usage_limit'),        default: false },
    { key: 'usage_per_customer',  label: t('discounts.usage_per_customer'), default: false },
    { key: 'usage_count',         label: t('discounts.usage_count'),        default: true  },
    { key: 'valid_from',          label: t('discounts.valid_from'),         default: false },
    { key: 'valid_until',         label: t('discounts.valid_until'),        default: true  },
    { key: 'is_active',           label: t('discounts.is_active'),          default: true  },
    { key: 'created_at',          label: t('global.created_at'),            default: true  },
    { key: 'creator',             label: t('global.created_by'),            default: false },
];

export const discountsExportEndpoints = () => ({
    excel: route('business_management.discounts.export_excel'),
    pdf:   route('business_management.discounts.export_pdf'),
    word:  route('business_management.discounts.export_word'),
    csv:   route('business_management.discounts.export_csv'),
});
