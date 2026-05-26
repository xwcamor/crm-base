export const leadSourcesExportableColumns = (t) => [
    { key: 'id',          label: 'ID',                              default: true  },
    { key: 'name',        label: t('lead_sources.name'),             default: true  },
    { key: 'description', label: t('lead_sources.description'),      default: false },
    { key: 'category',    label: t('lead_sources.category'),         default: true  },
    { key: 'sort_order',  label: t('lead_sources.sort_order'),       default: false },
    { key: 'is_active',   label: t('lead_sources.is_active'),        default: true  },
    { key: 'created_at',  label: t('global.created_at'),             default: true  },
    { key: 'creator',     label: t('global.created_by'),             default: false },
];

export const leadSourcesExportEndpoints = () => ({
    excel: route('business_management.lead_sources.export_excel'),
    pdf:   route('business_management.lead_sources.export_pdf'),
    word:  route('business_management.lead_sources.export_word'),
    csv:   route('business_management.lead_sources.export_csv'),
});
