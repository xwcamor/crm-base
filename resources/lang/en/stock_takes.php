<?php

return [
    'singular' => 'Stock take',
    'plural'   => 'Stock takes',
    'record'   => 'stock take',
    'records'  => 'stock takes',
    'new'      => 'New stock take',
    'id'       => 'No.',

    'index_title'      => 'Stock takes',
    'index_subtitle'   => 'Physical inventories and counts by warehouse. Completing one generates automatic stock adjustments.',
    'create_title'     => 'New stock take',
    'create_subtitle'  => 'Warehouse inventory — lines are generated from current stock.',
    'edit_title'       => 'Edit stock take',
    'delete_title'     => 'Delete stock take',
    'show_title'       => 'Stock take — Details',
    'trash_title'      => 'Stock takes trash',
    'form_create_hint' => 'Fill in the data to start a new stock take.',
    'empty_hint'       => 'No stock takes yet. Create the first one to inventory a warehouse.',

    'reference'        => 'Reference',
    'reference_hint'   => 'Internal stock-take code. Leave empty to let the system suggest the next sequential number.',
    'filter_reference' => 'Reference',
    'warehouse'        => 'Warehouse',
    'warehouse_hint'   => 'Warehouse the physical count runs against. Cannot be changed after creation.',
    'status'           => 'Status',
    'status_hint'      => 'Stock-take stage: draft, in progress, completed or cancelled. Completing it generates stock adjustments.',
    'started_at'       => 'Started',
    'completed_at'     => 'Completed',
    'completed_by'     => 'Completed by',
    'note'             => 'Note / purpose',
    'note_hint'        => 'Reason for the count (audit, month-end close, yearly inventory, etc.).',

    'status_options' => [
        'draft'       => 'Draft',
        'in_progress' => 'In progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ],

    'lines_title'      => 'Stock take lines',
    'lines_empty'      => 'This stock take has no associated products.',
    'line_product'     => 'Product',
    'line_sku'         => 'SKU',
    'line_qty_system'  => 'System',
    'line_qty_counted' => 'Counted',
    'line_variance'    => 'Variance',
    'line_note'        => 'Note',

    'created'                  => 'Stock take started with :count products.',
    'saved'                    => 'Stock take updated.',
    'deleted'                  => 'Stock take deleted.',
    'cannot_delete_completed'  => 'A completed stock take cannot be deleted (it already generated stock adjustments).',
    'adjustment_note'          => 'Physical count adjustment (variance: :variance)',

    'edit_hint'    => 'Edit this stock take',
    'delete_hint'  => 'Delete (goes to trash)',
    'restore_hint' => 'Will be available again in the main listing.',

    'delete_about' => 'You are about to delete ":name". It will go to the trash.',
    'deleted_description_required' => 'Provide a reason for the deletion.',
    'deleted_description_min'      => 'Reason must be at least 3 characters.',
    'deleted_description_max'      => 'Reason cannot exceed 1000 characters.',

    // Validation
    'reference_unique'   => 'A stock take with this reference already exists in the workspace.',
    'status_required'    => 'Status is required.',
    'warehouse_required' => 'Warehouse is required.',

    // Bulk
    'bulk_set_status' => 'Change status',

    // Export
    'export_filename'           => 'stock_takes_export',
    'import_template_filename'  => 'stock-takes-template.xlsx',
    'export_title'              => 'Stock Takes Report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets.',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Import
    'import_warehouse_required'  => 'Warehouse code is required.',
    'import_warehouse_not_found' => 'No warehouse found with that code.',
    'import_invalid_status'      => 'Invalid stock-take status.',

    // Edit All
    'edit_all_title'      => 'Stock takes — Edit All',
    'edit_all_subtitle'   => 'Edit reference and status of many stock takes at once. Click "Save all" to confirm.',
    'edit_all_changes'    => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all'   => 'Save all',
    'edit_all_discard'    => 'Discard changes',
    'edit_all_no_results' => 'No stock takes match the filter.',

    'tour' => [
        'step1_title' => 'Welcome to Stock Takes',
        'step1_body'  => 'Warehouse inventories with automatic adjustments. Quick tour in 1 minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search by reference, status, or warehouse.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite combo of filters + columns + sort and apply it with one click.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice is remembered.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word/CSV in background. Import stock takes from Excel/CSV with preview.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you change reference and status of multiple stock takes together.',
        'step7_title' => 'Favorites *',
        'step7_body'  => 'The star * marks a stock take as favorite. They always appear at the top.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with checkboxes and change status or delete in bulk.',
    ],
];
