<?php

return [
    'singular'      => 'Customer',
    'plural'        => 'Customers',
    'record'        => 'customer',
    'records'       => 'customers',
    'new'           => 'Create customer',
    'id'            => 'No.',

    'index_title'    => 'Customers',
    'index_subtitle' => 'Manage workspace customers.',
    'create_title'   => 'Create customer',
    'create_subtitle'=> 'Fill in the data to create a new record.',
    'edit_title'     => 'Edit customer',
    'delete_title'   => 'Delete customer',
    'show_title'     => 'Customer — Details',
    'trash_title'    => 'Customers trash',
    'form_create_hint' => 'Fill in the data to create a new customer.',
    'empty_hint'      => 'Create your first customer or import a batch from Excel.',
    'name_placeholder' => 'E.g.: Acme Inc.',

    'name'      => 'Name',
    'name_hint' => 'Customer name as it appears on invoices and documents. Unique per workspace.',
    // @scaffold:anchor description-lang
    // @scaffold:remove-begin commercial-labels
    'cod'       => 'Code',
    'cod_hint'  => 'Tax ID, internal code or commercial identifier. Unique per workspace.',
    'cod_placeholder' => 'E.g.: 20123456789',
    'country'           => 'Country',
    'country_hint'      => 'Customer tax residence country. Used for taxes and shipping.',
    'country_placeholder' => 'Select a country',
    // @scaffold:remove-end
    'is_active' => 'Status',
    'is_active_hint' => 'When inactive, the record is hidden from selectors when creating new records (existing ones keep working).',
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

    'export_filename'           => 'customers_export',
    'import_template_filename'  => 'customers-template.xlsx',
    'export_title'              => 'Customers Report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    'name_required'            => 'The name field is required.',
    'name_unique'              => 'This customer already exists.',
    'name_duplicate_in_batch'  => 'Duplicate name in the same batch.',
    'is_active_required'       => 'The status field is required.',

    'edit_all_title'    => 'Customer — Edit All',
    'edit_all_subtitle' => 'Edit name and status of multiple customers at once. Click "Save all" to confirm, "Cancel" to discard.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No customers match the filter.',

    'table_headers' => [
        'editable_name'   => 'Name (editable)',
        'editable_status' => 'Status (editable)',
    ],

    'tour' => [
        'step1_title' => 'Welcome to Customers',
        'step1_body'  => 'This is your customers module. Quick tour in under a minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by name, status, dates and ID. Active filters appear as chips above the table.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite filter + columns + sort combo and reapply with one click. Per-user.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice persists. Required ones cannot be hidden.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background — you will be notified. Import from Excel/CSV with preview.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you modify name and status across many records and save in one go.',
        'step7_title' => 'Favorites ★',
        'step7_body'  => 'The star ★ marks a row as a favorite. Favorites always show at the top of the list; each user has their own.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes — a bar appears to activate, deactivate, delete or restore.',
        'step9_title' => 'Need a refresher?',
        'step9_body'  => 'Reopen this tour anytime with the ? button. "Recent" in the avatar menu shows the last records you viewed.',
    ],
];
