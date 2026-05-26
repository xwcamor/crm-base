<?php

/*
|--------------------------------------------------------------------------
| Deliveries module config
|--------------------------------------------------------------------------
|
| Tunables del modulo Deliveries. Equivalente al `config/stock_takes.php`
| adaptado al dominio de entregas (fulfillment de sales orders).
*/

return [
    // Cantidad maxima de cambios permitidos en una sola operacion Edit-All.
    'edit_all_max' => env('DELIVERIES_EDIT_ALL_MAX', 200),

    // Umbral a partir del cual bulk delete/restore/set_status se dispatchean
    // a queue en lugar de ejecutarse sync.
    'bulk_async_threshold' => env('DELIVERIES_BULK_ASYNC_THRESHOLD', 200),

    // Memory limit aplicado al ini_set() dentro de los export jobs.
    'export_job_memory_limit' => env('DELIVERIES_EXPORT_MEMORY_LIMIT', '512M'),
];
