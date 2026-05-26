export const productVariantsExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                              default: true  },
    { key: 'name',       label: t('product_variants.name'),         default: true  },
    { key: 'sku',        label: t('product_variants.sku'),          default: true  },
    { key: 'barcode',    label: t('product_variants.barcode'),      default: false },
    { key: 'product',    label: t('product_variants.product'),      default: true  },
    { key: 'attributes', label: t('product_variants.attributes'),   default: false },
    { key: 'cost',       label: t('product_variants.cost'),         default: false },
    { key: 'price',      label: t('product_variants.price'),        default: true  },
    { key: 'sort_order', label: t('product_variants.sort_order'),   default: false },
    { key: 'is_active',  label: t('product_variants.is_active'),    default: true  },
    { key: 'created_at', label: t('global.created_at'),             default: true  },
    { key: 'creator',    label: t('global.created_by'),             default: false },
];

export const productVariantsExportEndpoints = () => ({
    excel: route('business_management.product_variants.export_excel'),
    pdf:   route('business_management.product_variants.export_pdf'),
    word:  route('business_management.product_variants.export_word'),
    csv:   route('business_management.product_variants.export_csv'),
});
