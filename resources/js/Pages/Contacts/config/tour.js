/**
 * Onboarding tour del módulo Contacts. Los selectores apuntan a data-tour="*"
 * en el template; si alguno no está montado al disparar el composable lo
 * saltea automáticamente.
 */
export const contactsTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('contacts.tour.step2_title'), description: t('contacts.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('contacts.tour.step3_title'), description: t('contacts.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('contacts.tour.step4_title'), description: t('contacts.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('contacts.tour.step5_title'), description: t('contacts.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('contacts.tour.step7_title'), description: t('contacts.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('contacts.tour.step8_title'), description: t('contacts.tour.step8_body') }},
];
