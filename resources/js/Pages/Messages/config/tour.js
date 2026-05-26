/**
 * Onboarding tour del modulo Messages. Los selectores apuntan a data-tour="*"
 * en el template; si alguno no esta montado al disparar el composable lo
 * saltea automaticamente.
 */
export const messagesTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('messages.tour.step_filters_title'),   description: t('messages.tour.step_filters_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('messages.tour.step_export_title'),    description: t('messages.tour.step_export_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('messages.tour.step_favorites_title'), description: t('messages.tour.step_favorites_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('messages.tour.step_bulk_title'),      description: t('messages.tour.step_bulk_body') }},
];
