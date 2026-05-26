<?php

use App\Http\Controllers\Communication\InboxController;
use App\Http\Controllers\Communication\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('communication')->name('communication.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Inbox — todos los users autenticados
    |--------------------------------------------------------------------------
    | El user solo ve mensajes donde es recipient (resuelto en message_recipients).
    */
    Route::get('inbox',                [InboxController::class, 'index'])->name('inbox.index');
    Route::post('inbox/mark-all-read', [InboxController::class, 'markAllRead'])->name('inbox.mark_all_read');
    Route::get('inbox/{slug}',         [InboxController::class, 'show'])->name('inbox.show');
    Route::post('inbox/{slug}/reply',  [InboxController::class, 'reply'])->name('inbox.reply');

    /*
    |--------------------------------------------------------------------------
    | Messages — super only (Tier 1 parity con DiscountController)
    |--------------------------------------------------------------------------
    | CRUD + trash + force-delete + edit-all + duplicate + bulk + exports +
    | imports. Toda la superficie clonada del patron Discount/Customer.
    */
    Route::middleware('role:super')->group(function () {

        // Trash + restore + force-delete (rutas mas especificas primero).
        Route::get('messages/trash',                  [MessageController::class, 'trash'])->name('messages.trash');
        Route::post('messages/bulk_restore',          [MessageController::class, 'bulkRestore'])->name('messages.bulk_restore');
        Route::post('messages/{slug}/restore',        [MessageController::class, 'restore'])->name('messages.restore');
        Route::get('messages/{slug}/restore',         fn () => redirect()->route('communication.messages.trash'));
        Route::delete('messages/{slug}/force_delete', [MessageController::class, 'forceDelete'])->name('messages.force_delete');

        // Exports (async via queue).
        Route::post('messages/export_csv',   [MessageController::class, 'exportCsv'])->name('messages.export_csv');
        Route::post('messages/export_excel', [MessageController::class, 'exportExcel'])->name('messages.export_excel');
        Route::post('messages/export_pdf',   [MessageController::class, 'exportPdf'])->name('messages.export_pdf');
        Route::post('messages/export_word',  [MessageController::class, 'exportWord'])->name('messages.export_word');

        // Imports.
        Route::post('messages/import',         [MessageController::class, 'import'])->name('messages.import');
        Route::get('messages/import_template', [MessageController::class, 'importTemplate'])->name('messages.import_template');

        // Bulk ops + undo (throttle para evitar abuso del bulk-delete).
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('messages/bulk_delete',     [MessageController::class, 'bulkDelete'])->name('messages.bulk_delete');
            Route::post('messages/bulk_set_active', [MessageController::class, 'bulkSetActive'])->name('messages.bulk_set_active');
        });
        Route::post('messages/undo_last_delete', [MessageController::class, 'undoLastDelete'])->name('messages.undo_last_delete');

        // Edit all.
        Route::get('messages/edit_all',         [MessageController::class, 'editAll'])->name('messages.edit_all');
        Route::post('messages/edit_all/update', [MessageController::class, 'editAllUpdate'])->name('messages.edit_all.update');

        // Create + store + duplicate.
        Route::get('messages/create',                [MessageController::class, 'create'])->name('messages.create');
        Route::post('messages',                      [MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{slug}/duplicate',     [MessageController::class, 'duplicate'])->name('messages.duplicate');

        // Edit + update.
        Route::get('messages/{slug}/edit',           [MessageController::class, 'edit'])->name('messages.edit');
        Route::put('messages/{slug}',                [MessageController::class, 'update'])->name('messages.update');

        // Delete (soft).
        Route::get('messages/{slug}/delete',         [MessageController::class, 'delete'])->name('messages.delete');
        Route::delete('messages/{slug}/deleteSave',  [MessageController::class, 'deleteSave'])->name('messages.deleteSave');

        // Index + show (show queda al final para que las rutas mas especificas matcheen primero).
        Route::get('messages',         [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{slug}',  [MessageController::class, 'show'])->name('messages.show');
    });
});
