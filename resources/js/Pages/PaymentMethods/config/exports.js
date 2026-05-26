export const paymentMethodsExportableColumns = (t) => [
    { key: 'id',                   label: 'ID',                                       default: true  },
    { key: 'name',                 label: t('payment_methods.name'),                  default: true  },
    { key: 'code',                 label: t('payment_methods.code'),                  default: true  },
    { key: 'description',          label: t('payment_methods.description'),           default: false },
    { key: 'integration_provider', label: t('payment_methods.integration_provider'),  default: false },
    { key: 'requires_reference',   label: t('payment_methods.requires_reference'),    default: false },
    { key: 'sort_order',           label: t('payment_methods.sort_order'),            default: false },
    { key: 'is_active',            label: t('payment_methods.is_active'),             default: true  },
    { key: 'created_at',           label: t('global.created_at'),                     default: true  },
    { key: 'creator',              label: t('global.created_by'),                     default: false },
];

export const paymentMethodsExportEndpoints = () => ({
    excel: route('business_management.payment_methods.export_excel'),
    pdf:   route('business_management.payment_methods.export_pdf'),
    word:  route('business_management.payment_methods.export_word'),
    csv:   route('business_management.payment_methods.export_csv'),
});
