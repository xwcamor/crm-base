/**
 * Onboarding tour del modulo StockTakes. Los selectores apuntan a
 * data-tour="*" en el template; si alguno no esta montado al disparar el
 * composable lo saltea automaticamente.
 */
export const stockTakesTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('stock_takes.tour.step2_title'), description: t('stock_takes.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('stock_takes.tour.step3_title'), description: t('stock_takes.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('stock_takes.tour.step4_title'), description: t('stock_takes.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('stock_takes.tour.step5_title'), description: t('stock_takes.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('stock_takes.tour.step7_title'), description: t('stock_takes.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('stock_takes.tour.step8_title'), description: t('stock_takes.tour.step8_body') }},
];
