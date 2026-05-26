/**
 * Onboarding tour del modulo Deliveries. Los selectores apuntan a
 * data-tour="*" en el template; si alguno no esta montado al disparar el
 * composable lo saltea automaticamente.
 */
export const deliveriesTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('deliveries.tour.step2_title'), description: t('deliveries.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('deliveries.tour.step3_title'), description: t('deliveries.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('deliveries.tour.step4_title'), description: t('deliveries.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('deliveries.tour.step5_title'), description: t('deliveries.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('deliveries.tour.step7_title'), description: t('deliveries.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('deliveries.tour.step8_title'), description: t('deliveries.tour.step8_body') }},
];
