<?php

/*
|--------------------------------------------------------------------------
| Stock Takes module config
|--------------------------------------------------------------------------
|
| Tunables del modulo StockTakes. Equivalente al `config/sales_orders.php`
| pero adaptado al dominio de conteos fisicos.
*/

return [
    // Cantidad maxima de cambios permitidos en una sola operacion Edit-All.
    'edit_all_max' => env('STOCK_TAKES_EDIT_ALL_MAX', 200),

    // Umbral a partir del cual bulk delete/restore/set_status se dispatchean
    // a queue en lugar de ejecutarse sync.
    'bulk_async_threshold' => env('STOCK_TAKES_BULK_ASYNC_THRESHOLD', 200),

    // Memory limit aplicado al ini_set() dentro de los export jobs.
    'export_job_memory_limit' => env('STOCK_TAKES_EXPORT_MEMORY_LIMIT', '512M'),
];
