/**
 * Onboarding tour del módulo Invoices. Los selectores apuntan a data-tour="*"
 * en el template; si alguno no está montado al disparar el composable lo
 * saltea automáticamente.
 */
export const invoicesTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('invoices.tour.step2_title'), description: t('invoices.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('invoices.tour.step3_title'), description: t('invoices.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('invoices.tour.step4_title'), description: t('invoices.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('invoices.tour.step5_title'), description: t('invoices.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('invoices.tour.step7_title'), description: t('invoices.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('invoices.tour.step8_title'), description: t('invoices.tour.step8_body') }},
];
