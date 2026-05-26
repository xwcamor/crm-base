/**
 * Columnas exportables del modulo Plans. Independientes de las visibles en
 * pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const plansExportableColumns = (t) => [
    { key: 'id',                     label: 'ID',                              default: true  },
    { key: 'slug',                   label: t('plans.slug'),                   default: true  },
    { key: 'name',                   label: t('plans.name'),                   default: true  },
    { key: 'tagline',                label: t('plans.tagline'),                default: false },
    { key: 'support_level',          label: t('plans.support_level'),          default: true  },
    { key: 'max_users',              label: t('plans.max_users'),              default: true  },
    { key: 'max_records_per_module', label: t('plans.max_records_per_module'), default: true  },
    { key: 'export_rate_limit',      label: t('plans.export_rate_limit'),      default: false },
    { key: 'price_monthly',          label: t('plans.price_monthly'),          default: true  },
    { key: 'price_yearly',           label: t('plans.price_yearly'),           default: true  },
    { key: 'currency',               label: t('plans.currency'),               default: false },
    { key: 'is_active',              label: t('plans.is_active'),              default: true  },
    { key: 'is_public',              label: t('plans.is_public'),              default: false },
    { key: 'sort_order',             label: t('plans.sort_order'),             default: false },
    { key: 'created_at',             label: t('global.created_at'),            default: true  },
    { key: 'creator',                label: t('global.created_by'),            default: false },
];

export const plansExportEndpoints = () => ({
    excel: route('system_management.plans.export_excel'),
    pdf:   route('system_management.plans.export_pdf'),
    word:  route('system_management.plans.export_word'),
    csv:   route('system_management.plans.export_csv'),
});
