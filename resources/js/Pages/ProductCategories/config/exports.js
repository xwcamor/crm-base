export const productCategoriesExportableColumns = (t) => [
    { key: 'id',          label: 'ID',                              default: true  },
    { key: 'name',        label: t('product_categories.name'),       default: true  },
    { key: 'description', label: t('product_categories.description'),default: false },
    { key: 'parent',      label: t('product_categories.parent'),     default: true  },
    { key: 'sort_order',  label: t('product_categories.sort_order'), default: false },
    { key: 'is_active',   label: t('product_categories.is_active'),  default: true  },
    { key: 'created_at',  label: t('global.created_at'),             default: true  },
    { key: 'creator',     label: t('global.created_by'),             default: false },
];

export const productCategoriesExportEndpoints = () => ({
    excel: route('business_management.product_categories.export_excel'),
    pdf:   route('business_management.product_categories.export_pdf'),
    word:  route('business_management.product_categories.export_word'),
    csv:   route('business_management.product_categories.export_csv'),
});
