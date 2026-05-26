<?php

return [
    'singular'      => 'Product',
    'plural'        => 'Products',
    'record'        => 'product',
    'records'       => 'products',
    'new'           => 'Create product',
    'id'            => 'No.',

    'index_title'    => 'Products',
    'index_subtitle' => 'Catalog of goods, services, subscriptions and bundles.',
    'create_title'   => 'Create product',
    'create_subtitle'=> 'New product/service for the catalog.',
    'edit_title'     => 'Edit product',
    'delete_title'   => 'Delete product',
    'show_title'     => 'Product — Details',
    'trash_title'    => 'Products trash',
    'form_create_hint' => 'Define a new product for the catalog.',
    'empty_hint'      => 'Create your first product or import from Excel.',
    'name_placeholder' => 'E.g.: Pro Annual License',

    // Identity
    'name'             => 'Name',
    'name_placeholder_form' => 'E.g.: Pro License — 50 users annual',
    'sku'              => 'SKU',
    'sku_placeholder'  => 'E.g.: LIC-PRO-50',
    'sku_hint'         => 'Unique internal product code. Identifies the product in orders, invoices, stock and reports. Use a consistent pattern (e.g.: CAT-PROD-VAR).',
    'barcode'          => 'Barcode',
    'barcode_placeholder' => 'EAN/UPC',
    'barcode_hint'     => 'Standard barcode (EAN-13, UPC-A, GTIN). Useful for retail with a barcode scanner.',
    'name_hint'        => 'Commercial name as it appears in quotes and invoices to the customer.',
    'description_hint' => 'Short description shown in lists and product pickers.',
    'long_description_hint' => 'Extended description for technical sheets, proposals, marketplace. Does not appear in quick lists.',
    'description'      => 'Short description',
    'description_placeholder' => 'Brief description for listings',
    'long_description' => 'Long description',
    'long_description_placeholder' => 'Details, technical specs, terms...',

    // Classification
    'category'         => 'Category',
    'category_placeholder' => 'Select category',
    'category_hint'    => 'Groups products for reports, filters and permissions. Managed in Catalogs → Product Categories.',
    'type'             => 'Type',
    'type_hint'        => 'Physical good = with stock; Service = hours/installation without stock; Subscription = recurring billing; Bundle = combo of multiple products sold as one.',
    'type_options' => [
        'good'         => 'Physical good',
        'service'      => 'Service',
        'subscription' => 'Subscription',
        'bundle'       => 'Bundle',
    ],
    'brand'            => 'Brand',
    'brand_placeholder'=> 'E.g.: Acme, Generic',
    'brand_hint'       => 'Manufacturer brand. Useful for filters, reports, and marketplace SEO.',

    // Pricing
    'cost'             => 'Acquisition cost',
    'cost_placeholder' => '0.00',
    'cost_hint'        => 'Estimated cost to acquire or produce the product. For local purchases this is typically the paid amount. For imports it is an estimate — the actual cost goes into "Final cost" once the product arrives.',
    'final_cost'       => 'Final cost',
    'final_cost_placeholder' => '0.00',
    'final_cost_hint'  => 'Real "landed" cost: includes freight, customs, agents, insurance. For imports this is only known when the product arrives at warehouse. For local purchases usually matches acquisition cost. Used for real profitability reports.',
    'list_price'       => 'Sale price',
    'list_price_placeholder' => '0.00',
    'list_price_hint'  => 'Public sale price. Can be overridden by price lists (wholesale, enterprise, etc.).',
    'currency'         => 'Currency',
    'currency_placeholder' => 'Inherits from tenant',
    'currency_hint'    => 'Currency for price and cost. If left empty, inherits the workspace default currency.',
    'margin'           => 'Margin',
    'margin_pct'       => 'Margin %',
    'margin_pct_hint'  => 'Percent margin auto-calculated from acquisition cost and sale price. Read-only.',
    'final_margin_pct' => 'Final margin %',
    'final_margin_pct_hint' => 'REAL margin auto-calculated from final (landed) cost and sale price. Reflects actual post-import profitability. Read-only.',

    // Tax
    'tax_class'        => 'Tax class',

    // Inventory
    'track_inventory'     => 'Track stock',
    'track_inventory_hint'=> 'If enabled, stock is decremented on sale. Only applies to physical goods.',
    'low_stock_threshold' => 'Low stock threshold',
    'low_stock_threshold_placeholder' => '0',
    'low_stock_threshold_hint' => 'Minimum quantity. If available stock falls below this value, an alert appears in dashboard and notifications.',

    // Subscription
    'billing_cycle_hint' => 'Frequency at which the subscription is auto-billed to the customer.',
    'billing_cycle'    => 'Billing cycle',
    'billing_cycle_options' => [
        'monthly'   => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly'    => 'Yearly',
    ],
    'billing_period'   => 'Every N cycles',
    'billing_period_hint' => 'E.g.: 1 = every month, 3 = every 3 months',

    // Dimensions
    'weight_kg' => 'Weight (kg)',
    'weight_kg_hint' => 'Packed product weight in kilograms. Used to compute shipping costs and waybills.',
    'length_cm' => 'Length (cm)',
    'length_cm_hint' => 'Packed product length in centimeters. Useful to compute volumetric shipping rates.',
    'width_cm'  => 'Width (cm)',
    'width_cm_hint' => 'Packed product width in centimeters. Useful to compute volumetric shipping rates.',
    'height_cm' => 'Height (cm)',
    'height_cm_hint' => 'Packed product height in centimeters. Useful to compute volumetric shipping rates.',

    // Others
    'image_url'   => 'Image URL',
    'image_url_hint' => 'Public URL of the product image. Shown in listings, detail pages and quotes/invoices. Recommended: 600×600 px minimum.',
    'external_id' => 'External ID',
    'external_id_hint' => 'Identifier of this product in external systems (ERP, e-commerce, marketplace). Used for sync via imports/exports or APIs.',

    // Form sections
    'section_general'        => 'General data',
    'section_classification' => 'Classification',
    'section_pricing'        => 'Pricing + currency',
    'section_inventory'      => 'Inventory',
    'section_subscription'   => 'Subscription',
    'section_shipping'       => 'Dimensions / shipping',
    'section_media'          => 'Image + IDs',

    'is_active'   => 'Status',
    'filter_name' => 'Name',

    'edit_hint'   => 'Edit this record',
    'delete_hint' => 'Delete (goes to trash)',
    'restore_hint'=> 'Will go back to the main list.',

    'created' => 'Record created.',
    'saved'   => 'Record updated.',
    'deleted' => 'Record deleted.',

    'delete_about'                 => 'You are about to delete ":name". It will go to the trash.',
    'deleted_description_required' => 'Provide a reason for the deletion.',
    'deleted_description_min'      => 'Reason must be at least 3 characters.',
    'deleted_description_max'      => 'Reason cannot exceed 1000 characters.',

    // Export
    'export_filename'           => 'products_export',
    'import_template_filename'  => 'products-template.xlsx',
    'export_title'              => 'Products Report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Validation
    'name_required'            => 'The product name is required.',
    'name_unique'              => 'A product with that name already exists.',
    'name_duplicate_in_batch'  => 'Duplicate name in the same batch.',
    'list_price_required'      => 'Sale price is required.',
    'type_required'            => 'Select product type.',
    'billing_cycle_required'   => 'Select billing cycle for subscriptions.',
    'is_active_required'       => 'The status field is required.',

    // Edit All
    'edit_all_title'    => 'Product — Edit All',
    'edit_all_subtitle' => 'Edit name and status of multiple products at once.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No products match the filter.',

    'table_headers' => [
        'editable_name'   => 'Name (editable)',
        'editable_status' => 'Status (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Welcome to Products',
        'step1_body'  => 'Your catalog: goods, services, subscriptions and bundles. Quick tour in 1 minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by name, SKU, type, category and status.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save filter + columns + sort combos and reuse them.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice persists.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background. Import from Excel/CSV with preview.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you modify name and status across many products at once.',
        'step7_title' => 'Favorites',
        'step7_body'  => 'Mark key products as favorites to keep them at the top.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes to activate, deactivate, delete or restore in bulk.',
        'step9_title' => 'Need a refresher?',
        'step9_body'  => 'Reopen this tour anytime with the ? button.',
    ],
];
