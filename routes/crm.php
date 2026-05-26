<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\PipelineController;
use App\Http\Controllers\Crm\PipelineStageController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CompanyController;

/*
|--------------------------------------------------------------------------
| Crm
|--------------------------------------------------------------------------
| Modulos generados con make:module. Cada modulo se gobierna por permisos
| Spatie: companies.view, companies.create, etc.
|
| ORDEN DE RUTAS CRITICO: las rutas con paths estaticos (companies/create,
| companies/trash, companies/export_*) DEBEN ir ANTES que companies/{company}.
*/

Route::prefix('crm')->name('crm.')->group(function () {

    // ── Companies ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('companies/trash',                  [CompanyController::class, 'trash'])->name('companies.trash');
        Route::post('companies/bulk_restore',          [CompanyController::class, 'bulkRestore'])->name('companies.bulk_restore');
        Route::post('companies/{slug}/restore',        [CompanyController::class, 'restore'])->name('companies.restore');
        Route::get('companies/{slug}/restore',         fn () => redirect()->route('crm.companies.trash'));
        Route::delete('companies/{slug}/force_delete', [CompanyController::class, 'forceDelete'])->name('companies.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:companies.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('companies/export_excel', [CompanyController::class, 'exportExcel'])->name('companies.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('companies/export_pdf',   [CompanyController::class, 'exportPdf'])->name('companies.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('companies/export_word',  [CompanyController::class, 'exportWord'])->name('companies.export_word');
        Route::middleware('throttle:5,1')
            ->post('companies/export_csv',   [CompanyController::class, 'exportCsv'])->name('companies.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:companies.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('companies/import',          [CompanyController::class, 'import'])->name('companies.import');
        Route::get('companies/import_template',  [CompanyController::class, 'importTemplate'])->name('companies.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:companies.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('companies/bulk_delete',     [CompanyController::class, 'bulkDelete'])->name('companies.bulk_delete');
        Route::post('companies/bulk_set_active', [CompanyController::class, 'bulkSetActive'])->name('companies.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:companies.delete')->group(function () {
        Route::post('companies/undo_last_delete', [CompanyController::class, 'undoLastDelete'])->name('companies.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:companies.edit')->group(function () {
        Route::get('companies/edit_all',         [CompanyController::class, 'editAll'])->name('companies.edit_all');
        Route::post('companies/edit_all/update', [CompanyController::class, 'editAllUpdate'])->name('companies.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:companies.create')->group(function () {
        Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('companies',       [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{company}/duplicate', [CompanyController::class, 'duplicate'])->name('companies.duplicate');
    });

    Route::middleware('permission:companies.view')->group(function () {
        Route::get('companies',                [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}',  [CompanyController::class, 'show'])->name('companies.show');
    });
    Route::middleware('permission:companies.edit')->group(function () {
        Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('companies/{company}',      [CompanyController::class, 'update'])->name('companies.update');
    });
    Route::middleware('permission:companies.delete')->group(function () {
        Route::get('companies/{company}/delete',        [CompanyController::class, 'delete'])->name('companies.delete');
        Route::delete('companies/{company}/deleteSave', [CompanyController::class, 'deleteSave'])->name('companies.deleteSave');
    });


    // ── Contacts ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('contacts/trash',                  [ContactController::class, 'trash'])->name('contacts.trash');
        Route::post('contacts/bulk_restore',          [ContactController::class, 'bulkRestore'])->name('contacts.bulk_restore');
        Route::post('contacts/{slug}/restore',        [ContactController::class, 'restore'])->name('contacts.restore');
        Route::get('contacts/{slug}/restore',         fn () => redirect()->route('crm.contacts.trash'));
        Route::delete('contacts/{slug}/force_delete', [ContactController::class, 'forceDelete'])->name('contacts.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:contacts.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('contacts/export_excel', [ContactController::class, 'exportExcel'])->name('contacts.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('contacts/export_pdf',   [ContactController::class, 'exportPdf'])->name('contacts.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('contacts/export_word',  [ContactController::class, 'exportWord'])->name('contacts.export_word');
        Route::middleware('throttle:5,1')
            ->post('contacts/export_csv',   [ContactController::class, 'exportCsv'])->name('contacts.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:contacts.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('contacts/import',          [ContactController::class, 'import'])->name('contacts.import');
        Route::get('contacts/import_template',  [ContactController::class, 'importTemplate'])->name('contacts.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:contacts.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('contacts/bulk_delete',     [ContactController::class, 'bulkDelete'])->name('contacts.bulk_delete');
        Route::post('contacts/bulk_set_active', [ContactController::class, 'bulkSetActive'])->name('contacts.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:contacts.delete')->group(function () {
        Route::post('contacts/undo_last_delete', [ContactController::class, 'undoLastDelete'])->name('contacts.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:contacts.edit')->group(function () {
        Route::get('contacts/edit_all',         [ContactController::class, 'editAll'])->name('contacts.edit_all');
        Route::post('contacts/edit_all/update', [ContactController::class, 'editAllUpdate'])->name('contacts.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:contacts.create')->group(function () {
        Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('contacts',       [ContactController::class, 'store'])->name('contacts.store');
        Route::post('contacts/{contact}/duplicate', [ContactController::class, 'duplicate'])->name('contacts.duplicate');
    });

    Route::middleware('permission:contacts.view')->group(function () {
        Route::get('contacts',                [ContactController::class, 'index'])->name('contacts.index');
        Route::get('contacts/{contact}',  [ContactController::class, 'show'])->name('contacts.show');
    });
    Route::middleware('permission:contacts.edit')->group(function () {
        Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::put('contacts/{contact}',      [ContactController::class, 'update'])->name('contacts.update');
    });
    Route::middleware('permission:contacts.delete')->group(function () {
        Route::get('contacts/{contact}/delete',        [ContactController::class, 'delete'])->name('contacts.delete');
        Route::delete('contacts/{contact}/deleteSave', [ContactController::class, 'deleteSave'])->name('contacts.deleteSave');
    });


    // ── Pipelines ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('pipelines/trash',                  [PipelineController::class, 'trash'])->name('pipelines.trash');
        Route::post('pipelines/bulk_restore',          [PipelineController::class, 'bulkRestore'])->name('pipelines.bulk_restore');
        Route::post('pipelines/{slug}/restore',        [PipelineController::class, 'restore'])->name('pipelines.restore');
        Route::get('pipelines/{slug}/restore',         fn () => redirect()->route('crm.pipelines.trash'));
        Route::delete('pipelines/{slug}/force_delete', [PipelineController::class, 'forceDelete'])->name('pipelines.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:pipelines.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('pipelines/export_excel', [PipelineController::class, 'exportExcel'])->name('pipelines.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('pipelines/export_pdf',   [PipelineController::class, 'exportPdf'])->name('pipelines.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('pipelines/export_word',  [PipelineController::class, 'exportWord'])->name('pipelines.export_word');
        Route::middleware('throttle:5,1')
            ->post('pipelines/export_csv',   [PipelineController::class, 'exportCsv'])->name('pipelines.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:pipelines.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('pipelines/import',          [PipelineController::class, 'import'])->name('pipelines.import');
        Route::get('pipelines/import_template',  [PipelineController::class, 'importTemplate'])->name('pipelines.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:pipelines.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('pipelines/bulk_delete',     [PipelineController::class, 'bulkDelete'])->name('pipelines.bulk_delete');
        Route::post('pipelines/bulk_set_active', [PipelineController::class, 'bulkSetActive'])->name('pipelines.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:pipelines.delete')->group(function () {
        Route::post('pipelines/undo_last_delete', [PipelineController::class, 'undoLastDelete'])->name('pipelines.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:pipelines.edit')->group(function () {
        Route::get('pipelines/edit_all',         [PipelineController::class, 'editAll'])->name('pipelines.edit_all');
        Route::post('pipelines/edit_all/update', [PipelineController::class, 'editAllUpdate'])->name('pipelines.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:pipelines.create')->group(function () {
        Route::get('pipelines/create', [PipelineController::class, 'create'])->name('pipelines.create');
        Route::post('pipelines',       [PipelineController::class, 'store'])->name('pipelines.store');
        Route::post('pipelines/{pipeline}/duplicate', [PipelineController::class, 'duplicate'])->name('pipelines.duplicate');
    });

    Route::middleware('permission:pipelines.view')->group(function () {
        Route::get('pipelines',                [PipelineController::class, 'index'])->name('pipelines.index');
        Route::get('pipelines/{pipeline}',  [PipelineController::class, 'show'])->name('pipelines.show');
    });
    Route::middleware('permission:pipelines.edit')->group(function () {
        Route::get('pipelines/{pipeline}/edit', [PipelineController::class, 'edit'])->name('pipelines.edit');
        Route::put('pipelines/{pipeline}',      [PipelineController::class, 'update'])->name('pipelines.update');
    });
    Route::middleware('permission:pipelines.delete')->group(function () {
        Route::get('pipelines/{pipeline}/delete',        [PipelineController::class, 'delete'])->name('pipelines.delete');
        Route::delete('pipelines/{pipeline}/deleteSave', [PipelineController::class, 'deleteSave'])->name('pipelines.deleteSave');
    });

    // ── Pipeline Stages (sub-recurso del Pipeline) ──
    // Permiso pipelines.edit — stages son hijos del pipeline, no modulo aparte.
    Route::middleware('permission:pipelines.edit')->group(function () {
        Route::post('pipelines/{pipeline}/stages',                  [PipelineStageController::class, 'store'])->name('pipelines.stages.store');
        Route::post('pipelines/{pipeline}/stages/reorder',          [PipelineStageController::class, 'reorder'])->name('pipelines.stages.reorder');
        Route::put('pipelines/{pipeline}/stages/{stage}',           [PipelineStageController::class, 'update'])->name('pipelines.stages.update');
        Route::delete('pipelines/{pipeline}/stages/{stage}',        [PipelineStageController::class, 'destroy'])->name('pipelines.stages.destroy');
    });

    // ── Activities (polimorfico: Deal/Company/Contact) ──
    // El parent se determina via activitable_type/activitable_id en el body.
    // Vista global en /crm/activities, embed en Show de cada entidad.
    Route::middleware('permission:activities.view')->group(function () {
        Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    });
    Route::middleware('permission:activities.create')->group(function () {
        Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    });
    Route::middleware('permission:activities.edit')->group(function () {
        Route::put('activities/{activity}',                  [ActivityController::class, 'update'])->name('activities.update');
        Route::post('activities/{activity}/complete',        [ActivityController::class, 'markComplete'])->name('activities.complete');
        Route::post('activities/{activity}/reopen',          [ActivityController::class, 'markPending'])->name('activities.reopen');
    });
    Route::middleware('permission:activities.delete')->group(function () {
        Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    });


    // ── Deals ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('deals/trash',                  [DealController::class, 'trash'])->name('deals.trash');
        Route::post('deals/bulk_restore',          [DealController::class, 'bulkRestore'])->name('deals.bulk_restore');
        Route::post('deals/{slug}/restore',        [DealController::class, 'restore'])->name('deals.restore');
        Route::get('deals/{slug}/restore',         fn () => redirect()->route('crm.deals.trash'));
        Route::delete('deals/{slug}/force_delete', [DealController::class, 'forceDelete'])->name('deals.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:deals.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('deals/export_excel', [DealController::class, 'exportExcel'])->name('deals.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('deals/export_pdf',   [DealController::class, 'exportPdf'])->name('deals.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('deals/export_word',  [DealController::class, 'exportWord'])->name('deals.export_word');
        Route::middleware('throttle:5,1')
            ->post('deals/export_csv',   [DealController::class, 'exportCsv'])->name('deals.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:deals.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('deals/import',          [DealController::class, 'import'])->name('deals.import');
        Route::get('deals/import_template',  [DealController::class, 'importTemplate'])->name('deals.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:deals.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('deals/bulk_delete',     [DealController::class, 'bulkDelete'])->name('deals.bulk_delete');
        Route::post('deals/bulk_set_active', [DealController::class, 'bulkSetActive'])->name('deals.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:deals.delete')->group(function () {
        Route::post('deals/undo_last_delete', [DealController::class, 'undoLastDelete'])->name('deals.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:deals.edit')->group(function () {
        Route::get('deals/edit_all',         [DealController::class, 'editAll'])->name('deals.edit_all');
        Route::post('deals/edit_all/update', [DealController::class, 'editAllUpdate'])->name('deals.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:deals.create')->group(function () {
        Route::get('deals/create', [DealController::class, 'create'])->name('deals.create');
        Route::post('deals',       [DealController::class, 'store'])->name('deals.store');
        Route::post('deals/{deal}/duplicate', [DealController::class, 'duplicate'])->name('deals.duplicate');
    });

    Route::middleware('permission:deals.view')->group(function () {
        Route::get('deals',                [DealController::class, 'index'])->name('deals.index');
        Route::get('deals/{deal}',  [DealController::class, 'show'])->name('deals.show');
    });
    Route::middleware('permission:deals.edit')->group(function () {
        Route::get('deals/{deal}/edit', [DealController::class, 'edit'])->name('deals.edit');
        Route::put('deals/{deal}',      [DealController::class, 'update'])->name('deals.update');
        // Drag-and-drop entre stages en la vista Kanban — endpoint dedicado
        // (no usa update general por: 1) permite tener su propio request con
        // solo stage_id + status, 2) puede registrar la transicion en
        // DealStageHistory en el futuro, 3) audit log queda mas claro).
        Route::post('deals/{deal}/change-stage', [DealController::class, 'changeStage'])->name('deals.change_stage');
    });
    Route::middleware('permission:deals.delete')->group(function () {
        Route::get('deals/{deal}/delete',        [DealController::class, 'delete'])->name('deals.delete');
        Route::delete('deals/{deal}/deleteSave', [DealController::class, 'deleteSave'])->name('deals.deleteSave');
    });
});
