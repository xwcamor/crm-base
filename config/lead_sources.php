<?php

/*
|--------------------------------------------------------------------------
| Lead Sources module — tunable knobs
|--------------------------------------------------------------------------
|
| Clon de config/product_categories.php adaptado al modulo Lead Sources
| (per-tenant). Ajusta los valores via env sin tocar codigo.
*/
return [
    'bulk_async_threshold' => env('LEAD_SOURCES_BULK_ASYNC_THRESHOLD', 200),
    'undo_window_seconds'  => env('LEAD_SOURCES_UNDO_WINDOW', 60),
    'recent_views_keep'    => env('LEAD_SOURCES_RECENTS_KEEP', 10),
    'per_page_options'     => [10, 25, 50, 100, 200],
    'per_page_default'     => 25,
    'edit_all_max'         => 200,

    'export_limits' => [
        'csv'   => env('LEAD_SOURCES_EXPORT_LIMIT_CSV',   0),
        'excel' => env('LEAD_SOURCES_EXPORT_LIMIT_EXCEL', 25000),
        'pdf'   => env('LEAD_SOURCES_EXPORT_LIMIT_PDF',   5000),
        'word'  => env('LEAD_SOURCES_EXPORT_LIMIT_WORD',  10000),
    ],

    'export_job_memory_limit' => env('LEAD_SOURCES_EXPORT_MEMORY', '512M'),
];
