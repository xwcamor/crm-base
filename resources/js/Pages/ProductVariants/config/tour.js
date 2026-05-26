export const productVariantsTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('product_variants.tour.step2_title'), description: t('product_variants.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('product_variants.tour.step3_title'), description: t('product_variants.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('product_variants.tour.step4_title'), description: t('product_variants.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('product_variants.tour.step5_title'), description: t('product_variants.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('product_variants.tour.step7_title'), description: t('product_variants.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('product_variants.tour.step8_title'), description: t('product_variants.tour.step8_body') }},
];
