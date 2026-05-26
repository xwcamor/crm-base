<?php

/*
|--------------------------------------------------------------------------
| Plans module — tunable knobs
|--------------------------------------------------------------------------
|
| Plans es super-only (catalogo de pricing tiers, no per-tenant). Clon de
| config/discounts.php adaptado. Como el dataset es chico (4-20 tiers) los
| limites son mas relajados, pero mantenemos la misma forma para que el
| frontend reuse los mismos composables.
*/
return [
    /**
     * Bulk operations — umbral por encima del cual la operacion se
     * dispatcha a queue en lugar de ejecutar inline. Plans rara vez tiene
     * mas de 20 filas, asi que casi siempre va inline.
     */
    'bulk_async_threshold' => env('PLANS_BULK_ASYNC_THRESHOLD', 50),

    /**
     * Undo despues de delete — segundos durante los cuales el usuario
     * puede hacer click en "Deshacer" para restaurar lo eliminado.
     */
    'undo_window_seconds' => env('PLANS_UNDO_WINDOW', 60),

    /**
     * Recent items — cuantos registros vistos guardar por usuario.
     */
    'recent_views_keep' => env('PLANS_RECENTS_KEEP', 10),

    /**
     * Per-page options — valores aceptados en el listado.
     */
    'per_page_options' => [10, 25, 50, 100],

    /**
     * Default per-page — el que arranca al entrar al modulo.
     */
    'per_page_default' => 25,

    /**
     * Edit All — maximo de filas editables a la vez en el batch.
     */
    'edit_all_max' => 100,

    /**
     * Export — limites por formato. Plans tipicamente exporta el catalogo
     * completo (no filtros), pero respetamos la misma estructura.
     */
    'export_limits' => [
        'csv'   => env('PLANS_EXPORT_LIMIT_CSV',   0),
        'excel' => env('PLANS_EXPORT_LIMIT_EXCEL', 25000),
        'pdf'   => env('PLANS_EXPORT_LIMIT_PDF',   5000),
        'word'  => env('PLANS_EXPORT_LIMIT_WORD',  10000),
    ],

    /**
     * Memory limit para los jobs de export. Sobreescribe el `memory_limit`
     * de PHP solo dentro del worker que ejecuta el job.
     */
    'export_job_memory_limit' => env('PLANS_EXPORT_MEMORY', '512M'),
];
