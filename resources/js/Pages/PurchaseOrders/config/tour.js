/**
 * Onboarding tour del módulo PurchaseOrders.
 */
export const purchaseOrdersTourSteps = (t) => [
    { element: '[data-tour="filters"]',       popover: { title: t('purchase_orders.tour.step2_title'), description: t('purchase_orders.tour.step2_body') }},
    { element: '[data-tour="saved-views"]',   popover: { title: t('purchase_orders.tour.step3_title'), description: t('purchase_orders.tour.step3_body') }},
    { element: '[data-tour="columns"]',       popover: { title: t('purchase_orders.tour.step4_title'), description: t('purchase_orders.tour.step4_body') }},
    { element: '[data-tour="export-import"]', popover: { title: t('purchase_orders.tour.step5_title'), description: t('purchase_orders.tour.step5_body') }},
    { element: '[data-tour="favorites"]',     popover: { title: t('purchase_orders.tour.step7_title'), description: t('purchase_orders.tour.step7_body') }},
    { element: '[data-tour="bulk"]',          popover: { title: t('purchase_orders.tour.step8_title'), description: t('purchase_orders.tour.step8_body') }},
];
