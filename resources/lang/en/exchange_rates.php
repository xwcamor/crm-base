<?php

return [
    'singular'      => 'Exchange rate',
    'plural'        => 'Exchange rates',
    'record'        => 'exchange rate',
    'records'       => 'exchange rates',
    'new'           => 'New rate',
    'id'            => 'No.',

    'index_title'    => 'Exchange rates',
    'index_subtitle' => 'FX history between currency pairs. Append-only in use: every update inserts a new row.',
    'create_title'   => 'New exchange rate',
    'create_subtitle'=> 'Register a rate in the history.',
    'edit_title'     => 'Edit exchange rate',
    'delete_title'   => 'Delete exchange rate',
    'show_title'     => 'Exchange rate — Details',
    'trash_title'    => 'Rates trash',
    'form_create_hint' => 'Fill in the data to register a new rate.',
    'empty_hint'     => 'No exchange rates yet — create the first one.',

    'base_code'      => 'Base currency',
    'base_code_hint' => 'Source currency of the conversion (the one you hold).',
    'quote_code'     => 'Quote currency',
    'quote_code_hint'=> 'Target currency of the conversion (the one you convert to).',
    'rate'           => 'Rate',
    'rate_hint'      => 'How many units of the quote currency equal 1 unit of the base. Supports up to 6 decimals.',
    'valid_at'       => 'Valid from',
    'valid_at_hint'  => 'Date and time this rate becomes effective. The most recent active rate is the one applied.',
    'source'         => 'Source',
    'source_hint'    => 'Data origin: manual, fixer.io, BCRA, openexchangerates or another provider.',
    'is_active'      => 'Status',
    'is_active_hint' => 'If inactive, this rate is not applied even if it is the most recent.',
    'filter_base'    => 'Base currency',
    'filter_quote'   => 'Quote currency',

    'pair'           => 'Pair',
    'pair_label'     => ':base / :quote',
    'pair_display'   => ':base/:quote @ :date',

    'edit_hint'   => 'Modify this rate',
    'delete_hint' => 'Delete (moves to trash)',
    'restore_hint'=> 'It will be available again in the main listing.',

    'created' => 'Rate created.',
    'saved'   => 'Rate updated.',
    'deleted' => 'Rate deleted.',

    'delete_about'                 => 'You are about to delete ":display". It will be moved to trash.',
    'deleted_description_required' => 'Provide a reason for the deletion.',
    'deleted_description_min'      => 'Reason must be at least 3 characters.',
    'deleted_description_max'      => 'Reason cannot exceed 1000 characters.',

    // Export
    'export_filename'           => 'exchange_rates_export',
    'import_template_filename'  => 'exchange-rates-template.xlsx',
    'export_title'              => 'Exchange rates report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Validation
    'base_code_required'       => 'Base currency is required.',
    'quote_code_required'      => 'Quote currency is required.',
    'rate_required'            => 'Rate is required.',
    'valid_at_required'        => 'Valid-from date is required.',
    'pair_valid_unique'        => 'A rate already exists for that pair at that date and time.',
    'is_active_required'       => 'Status is required.',

    // Force delete
    'force_delete_display_mismatch' => 'Confirmation does not match. Exact copy: ":expected".',
    'force_delete_display_prompt'   => 'To confirm, type exactly: :display',

    // Edit All
    'edit_all_title'    => 'Rates — Edit All',
    'edit_all_subtitle' => 'Edit rate and status of many rows at once. Click "Save all" to confirm, "Cancel" to discard.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No rates match the current filter.',

    'table_headers' => [
        'editable_rate'   => 'Rate (editable)',
        'editable_status' => 'Status (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Welcome to Exchange rates',
        'step1_body'  => 'This is your FX rates module. We show the key spots in under a minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by base, quote, source and status. Active filters show as chips above the table.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite combination of filters + columns + order and apply later with one click.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice is remembered. Mandatory ones cannot be hidden.',
        'step5_title' => 'Export and Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background. Import from Excel/CSV with a preview before commit.',
        'step7_title' => 'Favorites',
        'step7_body'  => 'The star marks a rate as favorite. Favorites always appear on top.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes — a bar appears to activate, deactivate, delete or restore in bulk.',
    ],
];
