/**
 * Onboarding tour del módulo Warehouses. Los selectores apuntan a data-tour="*"
 * en el template; si alguno no está montado al disparar el composable lo
 * saltea automáticamente.
 */
export const warehousesTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('warehouses.tour.step2_title'), description: t('warehouses.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('warehouses.tour.step3_title'), description: t('warehouses.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('warehouses.tour.step4_title'), description: t('warehouses.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('warehouses.tour.step5_title'), description: t('warehouses.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('warehouses.tour.step7_title'), description: t('warehouses.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('warehouses.tour.step8_title'), description: t('warehouses.tour.step8_body') }},
];
