<?php

use App\Http\Controllers\Reports\ReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
| Modulo de reportes agregados (read-only). Cinco vistas:
|   - sales-pipeline : embudo + valor por etapa + tiempo en stage
|   - win-rate       : win/loss por owner/source/stage + motivos
|   - revenue        : tendencia mensual + top empresas + por categoria
|   - activity       : productividad de sales reps + vencidas
|   - operations     : facturas vencidas + stock bajo + OCs pendientes
|
| Permiso unico: reports.view (read-only). Sin CRUD — no genera datos.
*/

Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
    Route::get('sales-pipeline', [ReportsController::class, 'salesPipeline'])->name('sales_pipeline');
    Route::get('win-rate',       [ReportsController::class, 'winRate'])->name('win_rate');
    Route::get('revenue',        [ReportsController::class, 'revenue'])->name('revenue');
    Route::get('activity',       [ReportsController::class, 'activity'])->name('activity');
    Route::get('operations',     [ReportsController::class, 'operations'])->name('operations');

    // Export del reporte actual (PDF / Excel). Mismo set de filtros que el
    // reporte interactivo — viajan via query string.
    Route::get('{report}/pdf',   [ReportsController::class, 'exportPdf'])->name('export_pdf')
        ->whereIn('report', ['sales_pipeline', 'win_rate', 'revenue', 'activity', 'operations']);
    Route::get('{report}/excel', [ReportsController::class, 'exportExcel'])->name('export_excel')
        ->whereIn('report', ['sales_pipeline', 'win_rate', 'revenue', 'activity', 'operations']);
});
