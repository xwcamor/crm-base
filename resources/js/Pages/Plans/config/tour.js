/**
 * Onboarding tour del modulo Plans. Los selectores apuntan a data-tour="*"
 * en el template; si alguno no esta montado al disparar el composable lo
 * saltea automaticamente.
 */
export const plansTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('plans.tour.step2_title'), description: t('plans.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('plans.tour.step3_title'), description: t('plans.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('plans.tour.step4_title'), description: t('plans.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('plans.tour.step5_title'), description: t('plans.tour.step5_body') }},
    { element: '[data-tour="edit-all"]',      popover: { title: t('plans.tour.step6_title'), description: t('plans.tour.step6_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('plans.tour.step8_title'), description: t('plans.tour.step8_body') }},
];
