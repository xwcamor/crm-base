<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessManagement\TaxClassController;
use App\Http\Controllers\BusinessManagement\WarehouseController;
use App\Http\Controllers\BusinessManagement\PaymentController;
use App\Http\Controllers\BusinessManagement\InvoiceController;
use App\Http\Controllers\BusinessManagement\QuoteController;
use App\Http\Controllers\BusinessManagement\ProductController;
use App\Http\Controllers\BusinessManagement\CustomerController;
use App\Http\Controllers\BusinessManagement\SalesOrderController;
use App\Http\Controllers\BusinessManagement\PurchaseOrderController;
use App\Http\Controllers\BusinessManagement\StockController;
use App\Http\Controllers\BusinessManagement\SubscriptionController;
use App\Http\Controllers\BusinessManagement\ExchangeRateController;
use App\Http\Controllers\BusinessManagement\DiscountController;
use App\Http\Controllers\BusinessManagement\PriceListController;
use App\Http\Controllers\BusinessManagement\DeliveryController;
use App\Http\Controllers\BusinessManagement\StockTakeController;
use App\Http\Controllers\BusinessManagement\ProductCategoryController;
use App\Http\Controllers\BusinessManagement\ProductVariantController;
use App\Http\Controllers\BusinessManagement\LeadSourceController;
use App\Http\Controllers\BusinessManagement\PaymentMethodController;
use App\Http\Controllers\Catalog\CatalogController;

/*
|--------------------------------------------------------------------------
| Business Management
|--------------------------------------------------------------------------
| Modulos de negocio del SaaS (no del core). Cada modulo se gobierna por
| permisos Spatie: customers.view, customers.create, etc. El admin del
| workspace asigna esos permisos a roles desde el modulo de Perfiles.
|
| Customers es el primer modulo real del SaaS, generado con make:module.
|
| ORDEN DE RUTAS CRITICO: las rutas con paths estaticos (customers/create,
| customers/trash, customers/export_*) DEBEN ir ANTES que customers/{customer}.
| Sin esto, Laravel hace route model binding con customer='create' y 404.
*/

Route::prefix('business_management')->name('business_management.')->group(function () {

    /**
     * Helper inline: agrega trash/restore/bulkRestore/forceDelete + exportCsv para un modulo.
     * El controller debe usar el trait HasTrashAndExport.
     */
    $addTrashAndExport = function (string $prefix, string $controller, string $permKey) {
        Route::middleware('role:super')->group(function () use ($prefix, $controller) {
            Route::get($prefix . '/trash',                  [$controller, 'trash'])->name($prefix . '.trash');
            Route::post($prefix . '/bulk_restore',          [$controller, 'bulkRestore'])->name($prefix . '.bulk_restore');
            Route::post($prefix . '/{slug}/restore',        [$controller, 'restore'])->name($prefix . '.restore');
            Route::delete($prefix . '/{slug}/force_delete', [$controller, 'forceDelete'])->name($prefix . '.force_delete');
        });
        Route::middleware(['permission:' . $permKey . '.view', 'plan_feature:export_csv', 'throttle:5,1'])
            ->post($prefix . '/export_csv', [$controller, 'exportCsv'])->name($prefix . '.export_csv');
    };

    // ── Customers ──

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('customers/trash',                  [CustomerController::class, 'trash'])->name('customers.trash');
        Route::post('customers/bulk_restore',          [CustomerController::class, 'bulkRestore'])->name('customers.bulk_restore');
        Route::post('customers/{slug}/restore',        [CustomerController::class, 'restore'])->name('customers.restore');
        Route::get('customers/{slug}/restore',         fn () => redirect()->route('business_management.customers.trash'));
        Route::delete('customers/{slug}/force_delete', [CustomerController::class, 'forceDelete'])->name('customers.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:customers.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('customers/export_excel', [CustomerController::class, 'exportExcel'])->name('customers.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('customers/export_pdf',   [CustomerController::class, 'exportPdf'])->name('customers.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('customers/export_word',  [CustomerController::class, 'exportWord'])->name('customers.export_word');
        Route::middleware('throttle:5,1') // export_csv libre en todos los planes
            ->post('customers/export_csv',   [CustomerController::class, 'exportCsv'])->name('customers.export_csv');
    });

    // 3) Imports (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:customers.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('customers/import',          [CustomerController::class, 'import'])->name('customers.import');
        Route::get('customers/import_template',  [CustomerController::class, 'importTemplate'])->name('customers.import_template');
    });

    // 4) Bulk operations (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:customers.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('customers/bulk_delete',     [CustomerController::class, 'bulkDelete'])->name('customers.bulk_delete');
        Route::post('customers/bulk_set_active', [CustomerController::class, 'bulkSetActive'])->name('customers.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window) — gated por permiso de delete.
    Route::middleware('permission:customers.delete')->group(function () {
        Route::post('customers/undo_last_delete', [CustomerController::class, 'undoLastDelete'])->name('customers.undo_last_delete');
    });

    // Edit All — batch edit de name + is_active (gated por permiso de edit).
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('customers/edit_all',         [CustomerController::class, 'editAll'])->name('customers.edit_all');
        Route::post('customers/edit_all/update', [CustomerController::class, 'editAllUpdate'])->name('customers.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO (create), despues los con {customer}.
    Route::middleware('permission:customers.create')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers',       [CustomerController::class, 'store'])->name('customers.store');
        Route::post('customers/{customer}/duplicate', [CustomerController::class, 'duplicate'])->name('customers.duplicate');
    });

    // Acciones con slug — DESPUES de los paths estaticos.
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers',            [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}',      [CustomerController::class, 'update'])->name('customers.update');
    });
    Route::middleware('permission:customers.delete')->group(function () {
        Route::get('customers/{customer}/delete',        [CustomerController::class, 'delete'])->name('customers.delete');
        Route::delete('customers/{customer}/deleteSave', [CustomerController::class, 'deleteSave'])->name('customers.deleteSave');
    });


    // ── Products ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('products/trash',                  [ProductController::class, 'trash'])->name('products.trash');
        Route::post('products/bulk_restore',          [ProductController::class, 'bulkRestore'])->name('products.bulk_restore');
        Route::post('products/{slug}/restore',        [ProductController::class, 'restore'])->name('products.restore');
        Route::get('products/{slug}/restore',         fn () => redirect()->route('business_management.products.trash'));
        Route::delete('products/{slug}/force_delete', [ProductController::class, 'forceDelete'])->name('products.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:products.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('products/export_excel', [ProductController::class, 'exportExcel'])->name('products.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('products/export_pdf',   [ProductController::class, 'exportPdf'])->name('products.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('products/export_word',  [ProductController::class, 'exportWord'])->name('products.export_word');
        Route::middleware('throttle:5,1')
            ->post('products/export_csv',   [ProductController::class, 'exportCsv'])->name('products.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:products.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('products/import',          [ProductController::class, 'import'])->name('products.import');
        Route::get('products/import_template',  [ProductController::class, 'importTemplate'])->name('products.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:products.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('products/bulk_delete',     [ProductController::class, 'bulkDelete'])->name('products.bulk_delete');
        Route::post('products/bulk_set_active', [ProductController::class, 'bulkSetActive'])->name('products.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:products.delete')->group(function () {
        Route::post('products/undo_last_delete', [ProductController::class, 'undoLastDelete'])->name('products.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:products.edit')->group(function () {
        Route::get('products/edit_all',         [ProductController::class, 'editAll'])->name('products.edit_all');
        Route::post('products/edit_all/update', [ProductController::class, 'editAllUpdate'])->name('products.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:products.create')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products',       [ProductController::class, 'store'])->name('products.store');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    });

    Route::middleware('permission:products.view')->group(function () {
        Route::get('products',                [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}',  [ProductController::class, 'show'])->name('products.show');
    });
    Route::middleware('permission:products.edit')->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}',      [ProductController::class, 'update'])->name('products.update');
    });
    Route::middleware('permission:products.delete')->group(function () {
        Route::get('products/{product}/delete',        [ProductController::class, 'delete'])->name('products.delete');
        Route::delete('products/{product}/deleteSave', [ProductController::class, 'deleteSave'])->name('products.deleteSave');
    });


    // ── Quotes ── (Tier 1 parity con Customer master template)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('quotes/trash',                  [QuoteController::class, 'trash'])->name('quotes.trash');
        Route::post('quotes/bulk_restore',          [QuoteController::class, 'bulkRestore'])->name('quotes.bulk_restore');
        Route::post('quotes/{slug}/restore',        [QuoteController::class, 'restore'])->name('quotes.restore');
        Route::get('quotes/{slug}/restore',         fn () => redirect()->route('business_management.quotes.trash'));
        Route::delete('quotes/{slug}/force_delete', [QuoteController::class, 'forceDelete'])->name('quotes.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:quotes.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('quotes/export_excel', [QuoteController::class, 'exportExcel'])->name('quotes.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('quotes/export_pdf',   [QuoteController::class, 'exportPdf'])->name('quotes.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('quotes/export_word',  [QuoteController::class, 'exportWord'])->name('quotes.export_word');
        Route::middleware('throttle:5,1')
            ->post('quotes/export_csv',   [QuoteController::class, 'exportCsv'])->name('quotes.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:quotes.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('quotes/import',          [QuoteController::class, 'import'])->name('quotes.import');
        Route::get('quotes/import_template',  [QuoteController::class, 'importTemplate'])->name('quotes.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:quotes.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('quotes/bulk_delete',     [QuoteController::class, 'bulkDelete'])->name('quotes.bulk_delete');
        Route::post('quotes/bulk_set_active', [QuoteController::class, 'bulkSetActive'])->name('quotes.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:quotes.delete')->group(function () {
        Route::post('quotes/undo_last_delete', [QuoteController::class, 'undoLastDelete'])->name('quotes.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:quotes.edit')->group(function () {
        Route::get('quotes/edit_all',         [QuoteController::class, 'editAll'])->name('quotes.edit_all');
        Route::post('quotes/edit_all/update', [QuoteController::class, 'editAllUpdate'])->name('quotes.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:quotes.create')->group(function () {
        Route::get('quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
        Route::post('quotes',       [QuoteController::class, 'store'])->name('quotes.store');
        Route::post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    });

    Route::middleware('permission:quotes.view')->group(function () {
        Route::get('quotes',           [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}',     [QuoteController::class, 'show'])->name('quotes.show');
        Route::get('quotes/{quote}/pdf', [QuoteController::class, 'showPdf'])->name('quotes.show_pdf');
    });
    Route::middleware('permission:quotes.edit')->group(function () {
        Route::get('quotes/{quote}/edit',            [QuoteController::class, 'edit'])->name('quotes.edit');
        Route::put('quotes/{quote}',                 [QuoteController::class, 'update'])->name('quotes.update');
        // Acciones de workflow propias de Quote (preservadas).
        Route::post('quotes/{quote}/send',           [QuoteController::class, 'send'])->name('quotes.send');
        Route::post('quotes/{quote}/accept',         [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quote}/reject',         [QuoteController::class, 'reject'])->name('quotes.reject');
        Route::post('quotes/{quote}/to_invoice',     [QuoteController::class, 'convertToInvoice'])->name('quotes.to_invoice');
        Route::post('quotes/{quote}/to_sales_order', [QuoteController::class, 'convertToSalesOrder'])->name('quotes.to_sales_order');
    });
    Route::middleware('permission:quotes.delete')->group(function () {
        Route::get('quotes/{quote}/delete',        [QuoteController::class, 'delete'])->name('quotes.delete');
        Route::delete('quotes/{quote}/deleteSave', [QuoteController::class, 'deleteSave'])->name('quotes.deleteSave');
        Route::delete('quotes/{quote}',            [QuoteController::class, 'destroy'])->name('quotes.destroy');
    });

    // ── Invoices ── (Tier 1 parity con Customer master template)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('invoices/trash',                  [InvoiceController::class, 'trash'])->name('invoices.trash');
        Route::post('invoices/bulk_restore',          [InvoiceController::class, 'bulkRestore'])->name('invoices.bulk_restore');
        Route::post('invoices/{slug}/restore',        [InvoiceController::class, 'restore'])->name('invoices.restore');
        Route::get('invoices/{slug}/restore',         fn () => redirect()->route('business_management.invoices.trash'));
        Route::delete('invoices/{slug}/force_delete', [InvoiceController::class, 'forceDelete'])->name('invoices.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:invoices.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('invoices/export_excel', [InvoiceController::class, 'exportExcel'])->name('invoices.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('invoices/export_pdf',   [InvoiceController::class, 'exportPdf'])->name('invoices.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('invoices/export_word',  [InvoiceController::class, 'exportWord'])->name('invoices.export_word');
        Route::middleware('throttle:5,1')
            ->post('invoices/export_csv',   [InvoiceController::class, 'exportCsv'])->name('invoices.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:invoices.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('invoices/import',          [InvoiceController::class, 'import'])->name('invoices.import');
        Route::get('invoices/import_template',  [InvoiceController::class, 'importTemplate'])->name('invoices.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:invoices.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('invoices/bulk_delete',     [InvoiceController::class, 'bulkDelete'])->name('invoices.bulk_delete');
        Route::post('invoices/bulk_set_active', [InvoiceController::class, 'bulkSetActive'])->name('invoices.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:invoices.delete')->group(function () {
        Route::post('invoices/undo_last_delete', [InvoiceController::class, 'undoLastDelete'])->name('invoices.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:invoices.edit')->group(function () {
        Route::get('invoices/edit_all',         [InvoiceController::class, 'editAll'])->name('invoices.edit_all');
        Route::post('invoices/edit_all/update', [InvoiceController::class, 'editAllUpdate'])->name('invoices.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:invoices.create')->group(function () {
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices',       [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    });

    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('invoices',               [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}',     [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'showPdf'])->name('invoices.show_pdf');
    });
    Route::middleware('permission:invoices.edit')->group(function () {
        Route::get('invoices/{invoice}/edit',    [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}',         [InvoiceController::class, 'update'])->name('invoices.update');
        // Accion de workflow propia de Invoice (preservada).
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });
    Route::middleware('permission:invoices.delete')->group(function () {
        Route::get('invoices/{invoice}/delete',        [InvoiceController::class, 'delete'])->name('invoices.delete');
        Route::delete('invoices/{invoice}/deleteSave', [InvoiceController::class, 'deleteSave'])->name('invoices.deleteSave');
        Route::delete('invoices/{invoice}',            [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });

    // ── Payments ── (Tier 1 parity con Customer master template)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('payments/trash',                  [PaymentController::class, 'trash'])->name('payments.trash');
        Route::post('payments/bulk_restore',          [PaymentController::class, 'bulkRestore'])->name('payments.bulk_restore');
        Route::post('payments/{slug}/restore',        [PaymentController::class, 'restore'])->name('payments.restore');
        Route::get('payments/{slug}/restore',         fn () => redirect()->route('business_management.payments.trash'));
        Route::delete('payments/{slug}/force_delete', [PaymentController::class, 'forceDelete'])->name('payments.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:payments.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('payments/export_excel', [PaymentController::class, 'exportExcel'])->name('payments.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('payments/export_pdf',   [PaymentController::class, 'exportPdf'])->name('payments.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('payments/export_word',  [PaymentController::class, 'exportWord'])->name('payments.export_word');
        Route::middleware('throttle:5,1')
            ->post('payments/export_csv',   [PaymentController::class, 'exportCsv'])->name('payments.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:payments.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('payments/import',          [PaymentController::class, 'import'])->name('payments.import');
        Route::get('payments/import_template',  [PaymentController::class, 'importTemplate'])->name('payments.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:payments.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('payments/bulk_delete',     [PaymentController::class, 'bulkDelete'])->name('payments.bulk_delete');
        Route::post('payments/bulk_set_active', [PaymentController::class, 'bulkSetActive'])->name('payments.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:payments.delete')->group(function () {
        Route::post('payments/undo_last_delete', [PaymentController::class, 'undoLastDelete'])->name('payments.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:payments.edit')->group(function () {
        Route::get('payments/edit_all',         [PaymentController::class, 'editAll'])->name('payments.edit_all');
        Route::post('payments/edit_all/update', [PaymentController::class, 'editAllUpdate'])->name('payments.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:payments.create')->group(function () {
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments',       [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/duplicate', [PaymentController::class, 'duplicate'])->name('payments.duplicate');
    });

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('payments',               [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}',     [PaymentController::class, 'show'])->name('payments.show');
    });
    Route::middleware('permission:payments.edit')->group(function () {
        Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('payments/{payment}',      [PaymentController::class, 'update'])->name('payments.update');
    });
    Route::middleware('permission:payments.delete')->group(function () {
        Route::get('payments/{payment}/delete',        [PaymentController::class, 'delete'])->name('payments.delete');
        Route::delete('payments/{payment}/deleteSave', [PaymentController::class, 'deleteSave'])->name('payments.deleteSave');
        Route::delete('payments/{payment}',            [PaymentController::class, 'destroy'])->name('payments.destroy');
    });

    // ── Warehouses (scaffold genera el bloque completo abajo) ──

    // ── Stock (read-only dashboard + kardex)
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('stock',           [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
    });

    // ── Sales Orders ──
    // Migrado a Tier 1 (parity con Customer master template).

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('sales_orders/trash',                  [SalesOrderController::class, 'trash'])->name('sales_orders.trash');
        Route::post('sales_orders/bulk_restore',          [SalesOrderController::class, 'bulkRestore'])->name('sales_orders.bulk_restore');
        Route::post('sales_orders/{slug}/restore',        [SalesOrderController::class, 'restore'])->name('sales_orders.restore');
        Route::get('sales_orders/{slug}/restore',         fn () => redirect()->route('business_management.sales_orders.trash'));
        Route::delete('sales_orders/{slug}/force_delete', [SalesOrderController::class, 'forceDelete'])->name('sales_orders.force_delete');
    });

    // 2) Exports (gated por plan_feature)
    Route::middleware('permission:sales_orders.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('sales_orders/export_excel', [SalesOrderController::class, 'exportExcel'])->name('sales_orders.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('sales_orders/export_pdf',   [SalesOrderController::class, 'exportPdf'])->name('sales_orders.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('sales_orders/export_word',  [SalesOrderController::class, 'exportWord'])->name('sales_orders.export_word');
        Route::middleware('throttle:5,1')
            ->post('sales_orders/export_csv',   [SalesOrderController::class, 'exportCsv'])->name('sales_orders.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:sales_orders.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('sales_orders/import',          [SalesOrderController::class, 'import'])->name('sales_orders.import');
        Route::get('sales_orders/import_template',  [SalesOrderController::class, 'importTemplate'])->name('sales_orders.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:sales_orders.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('sales_orders/bulk_delete',     [SalesOrderController::class, 'bulkDelete'])->name('sales_orders.bulk_delete');
        Route::post('sales_orders/bulk_set_active', [SalesOrderController::class, 'bulkSetActive'])->name('sales_orders.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:sales_orders.delete')->group(function () {
        Route::post('sales_orders/undo_last_delete', [SalesOrderController::class, 'undoLastDelete'])->name('sales_orders.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:sales_orders.edit')->group(function () {
        Route::get('sales_orders/edit_all',         [SalesOrderController::class, 'editAll'])->name('sales_orders.edit_all');
        Route::post('sales_orders/edit_all/update', [SalesOrderController::class, 'editAllUpdate'])->name('sales_orders.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:sales_orders.create')->group(function () {
        Route::get('sales_orders/create', [SalesOrderController::class, 'create'])->name('sales_orders.create');
        Route::post('sales_orders',       [SalesOrderController::class, 'store'])->name('sales_orders.store');
        Route::post('sales_orders/{sales_order}/duplicate', [SalesOrderController::class, 'duplicate'])->name('sales_orders.duplicate');
    });

    Route::middleware('permission:sales_orders.view')->group(function () {
        Route::get('sales_orders',                    [SalesOrderController::class, 'index'])->name('sales_orders.index');
        Route::get('sales_orders/{sales_order}',      [SalesOrderController::class, 'show'])->name('sales_orders.show');
        Route::get('sales_orders/{sales_order}/pdf',  [SalesOrderController::class, 'showPdf'])->name('sales_orders.show_pdf');
    });
    Route::middleware('permission:sales_orders.edit')->group(function () {
        Route::get('sales_orders/{sales_order}/edit', [SalesOrderController::class, 'edit'])->name('sales_orders.edit');
        Route::put('sales_orders/{sales_order}',      [SalesOrderController::class, 'update'])->name('sales_orders.update');
    });
    Route::middleware('permission:sales_orders.delete')->group(function () {
        Route::get('sales_orders/{sales_order}/delete',        [SalesOrderController::class, 'delete'])->name('sales_orders.delete');
        Route::delete('sales_orders/{sales_order}/deleteSave', [SalesOrderController::class, 'deleteSave'])->name('sales_orders.deleteSave');
        Route::delete('sales_orders/{sales_order}',            [SalesOrderController::class, 'destroy'])->name('sales_orders.destroy');
    });

    // ── Purchase Orders ── (Tier 1 parity con Customer)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('purchase_orders/trash',                  [PurchaseOrderController::class, 'trash'])->name('purchase_orders.trash');
        Route::post('purchase_orders/bulk_restore',          [PurchaseOrderController::class, 'bulkRestore'])->name('purchase_orders.bulk_restore');
        Route::post('purchase_orders/{slug}/restore',        [PurchaseOrderController::class, 'restore'])->name('purchase_orders.restore');
        Route::get('purchase_orders/{slug}/restore',         fn () => redirect()->route('business_management.purchase_orders.trash'));
        Route::delete('purchase_orders/{slug}/force_delete', [PurchaseOrderController::class, 'forceDelete'])->name('purchase_orders.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:purchase_orders.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('purchase_orders/export_excel', [PurchaseOrderController::class, 'exportExcel'])->name('purchase_orders.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('purchase_orders/export_pdf',   [PurchaseOrderController::class, 'exportPdf'])->name('purchase_orders.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('purchase_orders/export_word',  [PurchaseOrderController::class, 'exportWord'])->name('purchase_orders.export_word');
        Route::middleware('throttle:5,1')
            ->post('purchase_orders/export_csv',   [PurchaseOrderController::class, 'exportCsv'])->name('purchase_orders.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:purchase_orders.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('purchase_orders/import',          [PurchaseOrderController::class, 'import'])->name('purchase_orders.import');
        Route::get('purchase_orders/import_template',  [PurchaseOrderController::class, 'importTemplate'])->name('purchase_orders.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:purchase_orders.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('purchase_orders/bulk_delete',     [PurchaseOrderController::class, 'bulkDelete'])->name('purchase_orders.bulk_delete');
        Route::post('purchase_orders/bulk_set_active', [PurchaseOrderController::class, 'bulkSetActive'])->name('purchase_orders.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:purchase_orders.delete')->group(function () {
        Route::post('purchase_orders/undo_last_delete', [PurchaseOrderController::class, 'undoLastDelete'])->name('purchase_orders.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:purchase_orders.edit')->group(function () {
        Route::get('purchase_orders/edit_all',         [PurchaseOrderController::class, 'editAll'])->name('purchase_orders.edit_all');
        Route::post('purchase_orders/edit_all/update', [PurchaseOrderController::class, 'editAllUpdate'])->name('purchase_orders.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:purchase_orders.create')->group(function () {
        Route::get('purchase_orders/create',                          [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
        Route::post('purchase_orders',                                [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
        Route::post('purchase_orders/{purchase_order}/duplicate',     [PurchaseOrderController::class, 'duplicate'])->name('purchase_orders.duplicate');
    });

    Route::middleware('permission:purchase_orders.view')->group(function () {
        Route::get('purchase_orders',                    [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
        Route::get('purchase_orders/{purchase_order}',   [PurchaseOrderController::class, 'show'])->name('purchase_orders.show');
    });
    Route::middleware('permission:purchase_orders.edit')->group(function () {
        Route::get('purchase_orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase_orders.edit');
        Route::put('purchase_orders/{purchase_order}',      [PurchaseOrderController::class, 'update'])->name('purchase_orders.update');
    });
    Route::middleware('permission:purchase_orders.delete')->group(function () {
        Route::get('purchase_orders/{purchase_order}/delete',        [PurchaseOrderController::class, 'delete'])->name('purchase_orders.delete');
        Route::delete('purchase_orders/{purchase_order}/deleteSave', [PurchaseOrderController::class, 'deleteSave'])->name('purchase_orders.deleteSave');
        Route::delete('purchase_orders/{purchase_order}',            [PurchaseOrderController::class, 'destroy'])->name('purchase_orders.destroy');
    });

    // ── Tax Classes (scaffold genera el bloque completo abajo) ──

    // ── Lead Sources ──
    // Tier 1 parity con Customer/Discount master template. Antes vivia en
    // CatalogController; ahora tiene su propio controller, service, jobs,
    // exports, imports, edit-all, soft delete, audit, favoritos.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('lead_sources/trash',                  [LeadSourceController::class, 'trash'])->name('lead_sources.trash');
        Route::post('lead_sources/bulk_restore',          [LeadSourceController::class, 'bulkRestore'])->name('lead_sources.bulk_restore');
        Route::post('lead_sources/{slug}/restore',        [LeadSourceController::class, 'restore'])->name('lead_sources.restore');
        Route::get('lead_sources/{slug}/restore',         fn () => redirect()->route('business_management.lead_sources.trash'));
        Route::delete('lead_sources/{slug}/force_delete', [LeadSourceController::class, 'forceDelete'])->name('lead_sources.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:lead_sources.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('lead_sources/export_excel', [LeadSourceController::class, 'exportExcel'])->name('lead_sources.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('lead_sources/export_pdf',   [LeadSourceController::class, 'exportPdf'])->name('lead_sources.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('lead_sources/export_word',  [LeadSourceController::class, 'exportWord'])->name('lead_sources.export_word');
        Route::middleware('throttle:5,1')
            ->post('lead_sources/export_csv',   [LeadSourceController::class, 'exportCsv'])->name('lead_sources.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:lead_sources.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('lead_sources/import',          [LeadSourceController::class, 'import'])->name('lead_sources.import');
        Route::get('lead_sources/import_template',  [LeadSourceController::class, 'importTemplate'])->name('lead_sources.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:lead_sources.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('lead_sources/bulk_delete',     [LeadSourceController::class, 'bulkDelete'])->name('lead_sources.bulk_delete');
        Route::post('lead_sources/bulk_set_active', [LeadSourceController::class, 'bulkSetActive'])->name('lead_sources.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:lead_sources.delete')->group(function () {
        Route::post('lead_sources/undo_last_delete', [LeadSourceController::class, 'undoLastDelete'])->name('lead_sources.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:lead_sources.edit')->group(function () {
        Route::get('lead_sources/edit_all',         [LeadSourceController::class, 'editAll'])->name('lead_sources.edit_all');
        Route::post('lead_sources/edit_all/update', [LeadSourceController::class, 'editAllUpdate'])->name('lead_sources.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:lead_sources.create')->group(function () {
        Route::get('lead_sources/create', [LeadSourceController::class, 'create'])->name('lead_sources.create');
        Route::post('lead_sources',       [LeadSourceController::class, 'store'])->name('lead_sources.store');
        Route::post('lead_sources/{lead_source}/duplicate', [LeadSourceController::class, 'duplicate'])->name('lead_sources.duplicate');
    });

    Route::middleware('permission:lead_sources.view')->group(function () {
        Route::get('lead_sources',                   [LeadSourceController::class, 'index'])->name('lead_sources.index');
        Route::get('lead_sources/{lead_source}',     [LeadSourceController::class, 'show'])->name('lead_sources.show');
    });
    Route::middleware('permission:lead_sources.edit')->group(function () {
        Route::get('lead_sources/{lead_source}/edit', [LeadSourceController::class, 'edit'])->name('lead_sources.edit');
        Route::put('lead_sources/{lead_source}',      [LeadSourceController::class, 'update'])->name('lead_sources.update');
    });
    Route::middleware('permission:lead_sources.delete')->group(function () {
        Route::get('lead_sources/{lead_source}/delete',        [LeadSourceController::class, 'delete'])->name('lead_sources.delete');
        Route::delete('lead_sources/{lead_source}/deleteSave', [LeadSourceController::class, 'deleteSave'])->name('lead_sources.deleteSave');
    });

    // ── Payment Methods ──
    // Tier 1 parity con Customer/Discount/ProductCategory master template. Antes
    // vivia en CatalogController (catalog-lite); ahora tiene su propio controller,
    // service, jobs, exports, imports, edit-all, soft delete, audit, favoritos.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('payment_methods/trash',                  [PaymentMethodController::class, 'trash'])->name('payment_methods.trash');
        Route::post('payment_methods/bulk_restore',          [PaymentMethodController::class, 'bulkRestore'])->name('payment_methods.bulk_restore');
        Route::post('payment_methods/{slug}/restore',        [PaymentMethodController::class, 'restore'])->name('payment_methods.restore');
        Route::get('payment_methods/{slug}/restore',         fn () => redirect()->route('business_management.payment_methods.trash'));
        Route::delete('payment_methods/{slug}/force_delete', [PaymentMethodController::class, 'forceDelete'])->name('payment_methods.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:payment_methods.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('payment_methods/export_excel', [PaymentMethodController::class, 'exportExcel'])->name('payment_methods.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('payment_methods/export_pdf',   [PaymentMethodController::class, 'exportPdf'])->name('payment_methods.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('payment_methods/export_word',  [PaymentMethodController::class, 'exportWord'])->name('payment_methods.export_word');
        Route::middleware('throttle:5,1')
            ->post('payment_methods/export_csv',   [PaymentMethodController::class, 'exportCsv'])->name('payment_methods.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:payment_methods.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('payment_methods/import',          [PaymentMethodController::class, 'import'])->name('payment_methods.import');
        Route::get('payment_methods/import_template',  [PaymentMethodController::class, 'importTemplate'])->name('payment_methods.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:payment_methods.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('payment_methods/bulk_delete',     [PaymentMethodController::class, 'bulkDelete'])->name('payment_methods.bulk_delete');
        Route::post('payment_methods/bulk_set_active', [PaymentMethodController::class, 'bulkSetActive'])->name('payment_methods.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:payment_methods.delete')->group(function () {
        Route::post('payment_methods/undo_last_delete', [PaymentMethodController::class, 'undoLastDelete'])->name('payment_methods.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:payment_methods.edit')->group(function () {
        Route::get('payment_methods/edit_all',         [PaymentMethodController::class, 'editAll'])->name('payment_methods.edit_all');
        Route::post('payment_methods/edit_all/update', [PaymentMethodController::class, 'editAllUpdate'])->name('payment_methods.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:payment_methods.create')->group(function () {
        Route::get('payment_methods/create', [PaymentMethodController::class, 'create'])->name('payment_methods.create');
        Route::post('payment_methods',       [PaymentMethodController::class, 'store'])->name('payment_methods.store');
        Route::post('payment_methods/{payment_method}/duplicate', [PaymentMethodController::class, 'duplicate'])->name('payment_methods.duplicate');
    });

    Route::middleware('permission:payment_methods.view')->group(function () {
        Route::get('payment_methods',                       [PaymentMethodController::class, 'index'])->name('payment_methods.index');
        Route::get('payment_methods/{payment_method}',      [PaymentMethodController::class, 'show'])->name('payment_methods.show');
    });
    Route::middleware('permission:payment_methods.edit')->group(function () {
        Route::get('payment_methods/{payment_method}/edit', [PaymentMethodController::class, 'edit'])->name('payment_methods.edit');
        Route::put('payment_methods/{payment_method}',      [PaymentMethodController::class, 'update'])->name('payment_methods.update');
    });
    Route::middleware('permission:payment_methods.delete')->group(function () {
        Route::get('payment_methods/{payment_method}/delete',        [PaymentMethodController::class, 'delete'])->name('payment_methods.delete');
        Route::delete('payment_methods/{payment_method}/deleteSave', [PaymentMethodController::class, 'deleteSave'])->name('payment_methods.deleteSave');
    });

    // ── Product Categories ──
    // Tier 1 parity con Customer/Discount master template. Antes vivia en
    // CatalogController; ahora tiene su propio controller, service, jobs,
    // exports, imports, edit-all, soft delete, audit, favoritos.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('product_categories/trash',                  [ProductCategoryController::class, 'trash'])->name('product_categories.trash');
        Route::post('product_categories/bulk_restore',          [ProductCategoryController::class, 'bulkRestore'])->name('product_categories.bulk_restore');
        Route::post('product_categories/{slug}/restore',        [ProductCategoryController::class, 'restore'])->name('product_categories.restore');
        Route::get('product_categories/{slug}/restore',         fn () => redirect()->route('business_management.product_categories.trash'));
        Route::delete('product_categories/{slug}/force_delete', [ProductCategoryController::class, 'forceDelete'])->name('product_categories.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:product_categories.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('product_categories/export_excel', [ProductCategoryController::class, 'exportExcel'])->name('product_categories.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('product_categories/export_pdf',   [ProductCategoryController::class, 'exportPdf'])->name('product_categories.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('product_categories/export_word',  [ProductCategoryController::class, 'exportWord'])->name('product_categories.export_word');
        Route::middleware('throttle:5,1')
            ->post('product_categories/export_csv',   [ProductCategoryController::class, 'exportCsv'])->name('product_categories.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:product_categories.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('product_categories/import',          [ProductCategoryController::class, 'import'])->name('product_categories.import');
        Route::get('product_categories/import_template',  [ProductCategoryController::class, 'importTemplate'])->name('product_categories.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:product_categories.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('product_categories/bulk_delete',     [ProductCategoryController::class, 'bulkDelete'])->name('product_categories.bulk_delete');
        Route::post('product_categories/bulk_set_active', [ProductCategoryController::class, 'bulkSetActive'])->name('product_categories.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:product_categories.delete')->group(function () {
        Route::post('product_categories/undo_last_delete', [ProductCategoryController::class, 'undoLastDelete'])->name('product_categories.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:product_categories.edit')->group(function () {
        Route::get('product_categories/edit_all',         [ProductCategoryController::class, 'editAll'])->name('product_categories.edit_all');
        Route::post('product_categories/edit_all/update', [ProductCategoryController::class, 'editAllUpdate'])->name('product_categories.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:product_categories.create')->group(function () {
        Route::get('product_categories/create', [ProductCategoryController::class, 'create'])->name('product_categories.create');
        Route::post('product_categories',       [ProductCategoryController::class, 'store'])->name('product_categories.store');
        Route::post('product_categories/{product_category}/duplicate', [ProductCategoryController::class, 'duplicate'])->name('product_categories.duplicate');
    });

    Route::middleware('permission:product_categories.view')->group(function () {
        Route::get('product_categories',            [ProductCategoryController::class, 'index'])->name('product_categories.index');
        Route::get('product_categories/{product_category}', [ProductCategoryController::class, 'show'])->name('product_categories.show');
    });
    Route::middleware('permission:product_categories.edit')->group(function () {
        Route::get('product_categories/{product_category}/edit', [ProductCategoryController::class, 'edit'])->name('product_categories.edit');
        Route::put('product_categories/{product_category}',      [ProductCategoryController::class, 'update'])->name('product_categories.update');
    });
    Route::middleware('permission:product_categories.delete')->group(function () {
        Route::get('product_categories/{product_category}/delete',        [ProductCategoryController::class, 'delete'])->name('product_categories.delete');
        Route::delete('product_categories/{product_category}/deleteSave', [ProductCategoryController::class, 'deleteSave'])->name('product_categories.deleteSave');
    });

    // ── Product Variants ──
    // Tier 1 parity con Customer/Discount/ProductCategory master template.
    // Antes vivia en CatalogController; ahora tiene su propio controller,
    // service, jobs, exports, imports, edit-all, soft delete, audit, favoritos.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('product_variants/trash',                  [ProductVariantController::class, 'trash'])->name('product_variants.trash');
        Route::post('product_variants/bulk_restore',          [ProductVariantController::class, 'bulkRestore'])->name('product_variants.bulk_restore');
        Route::post('product_variants/{slug}/restore',        [ProductVariantController::class, 'restore'])->name('product_variants.restore');
        Route::get('product_variants/{slug}/restore',         fn () => redirect()->route('business_management.product_variants.trash'));
        Route::delete('product_variants/{slug}/force_delete', [ProductVariantController::class, 'forceDelete'])->name('product_variants.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:product_variants.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('product_variants/export_excel', [ProductVariantController::class, 'exportExcel'])->name('product_variants.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('product_variants/export_pdf',   [ProductVariantController::class, 'exportPdf'])->name('product_variants.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('product_variants/export_word',  [ProductVariantController::class, 'exportWord'])->name('product_variants.export_word');
        Route::middleware('throttle:5,1')
            ->post('product_variants/export_csv',   [ProductVariantController::class, 'exportCsv'])->name('product_variants.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:product_variants.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('product_variants/import',          [ProductVariantController::class, 'import'])->name('product_variants.import');
        Route::get('product_variants/import_template',  [ProductVariantController::class, 'importTemplate'])->name('product_variants.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:product_variants.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('product_variants/bulk_delete',     [ProductVariantController::class, 'bulkDelete'])->name('product_variants.bulk_delete');
        Route::post('product_variants/bulk_set_active', [ProductVariantController::class, 'bulkSetActive'])->name('product_variants.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:product_variants.delete')->group(function () {
        Route::post('product_variants/undo_last_delete', [ProductVariantController::class, 'undoLastDelete'])->name('product_variants.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:product_variants.edit')->group(function () {
        Route::get('product_variants/edit_all',         [ProductVariantController::class, 'editAll'])->name('product_variants.edit_all');
        Route::post('product_variants/edit_all/update', [ProductVariantController::class, 'editAllUpdate'])->name('product_variants.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:product_variants.create')->group(function () {
        Route::get('product_variants/create', [ProductVariantController::class, 'create'])->name('product_variants.create');
        Route::post('product_variants',       [ProductVariantController::class, 'store'])->name('product_variants.store');
        Route::post('product_variants/{product_variant}/duplicate', [ProductVariantController::class, 'duplicate'])->name('product_variants.duplicate');
    });

    Route::middleware('permission:product_variants.view')->group(function () {
        Route::get('product_variants',            [ProductVariantController::class, 'index'])->name('product_variants.index');
        Route::get('product_variants/{product_variant}', [ProductVariantController::class, 'show'])->name('product_variants.show');
    });
    Route::middleware('permission:product_variants.edit')->group(function () {
        Route::get('product_variants/{product_variant}/edit', [ProductVariantController::class, 'edit'])->name('product_variants.edit');
        Route::put('product_variants/{product_variant}',      [ProductVariantController::class, 'update'])->name('product_variants.update');
    });
    Route::middleware('permission:product_variants.delete')->group(function () {
        Route::get('product_variants/{product_variant}/delete',        [ProductVariantController::class, 'delete'])->name('product_variants.delete');
        Route::delete('product_variants/{product_variant}/deleteSave', [ProductVariantController::class, 'deleteSave'])->name('product_variants.deleteSave');
    });

    // Industries — global, super only
    Route::middleware('role:super')->group(function () {
        Route::get('industries', [CatalogController::class, 'industries'])->name('industries.index');
        Route::post('industries', [CatalogController::class, 'industryStore'])->name('industries.store');
        Route::put('industries/{industry}', [CatalogController::class, 'industryUpdate'])->name('industries.update');
        Route::delete('industries/{industry}', [CatalogController::class, 'industryDestroy'])->name('industries.destroy');
    });

    // ── Subscriptions (super only — historial de planes de tenants)
    Route::middleware('role:super')->group(function () {
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    });

    // ── Exchange Rates ──
    // Tier 1 parity con Customer/Discount master template.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('exchange_rates/trash',                  [ExchangeRateController::class, 'trash'])->name('exchange_rates.trash');
        Route::post('exchange_rates/bulk_restore',          [ExchangeRateController::class, 'bulkRestore'])->name('exchange_rates.bulk_restore');
        Route::post('exchange_rates/{slug}/restore',        [ExchangeRateController::class, 'restore'])->name('exchange_rates.restore');
        Route::get('exchange_rates/{slug}/restore',         fn () => redirect()->route('business_management.exchange_rates.trash'));
        Route::delete('exchange_rates/{slug}/force_delete', [ExchangeRateController::class, 'forceDelete'])->name('exchange_rates.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:exchange_rates.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('exchange_rates/export_excel', [ExchangeRateController::class, 'exportExcel'])->name('exchange_rates.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('exchange_rates/export_pdf',   [ExchangeRateController::class, 'exportPdf'])->name('exchange_rates.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('exchange_rates/export_word',  [ExchangeRateController::class, 'exportWord'])->name('exchange_rates.export_word');
        Route::middleware('throttle:5,1')
            ->post('exchange_rates/export_csv',   [ExchangeRateController::class, 'exportCsv'])->name('exchange_rates.export_csv');
    });

    // 3) Imports (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:exchange_rates.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('exchange_rates/import',          [ExchangeRateController::class, 'import'])->name('exchange_rates.import');
        Route::get('exchange_rates/import_template',  [ExchangeRateController::class, 'importTemplate'])->name('exchange_rates.import_template');
    });

    // 4) Bulk operations (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:exchange_rates.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('exchange_rates/bulk_delete',     [ExchangeRateController::class, 'bulkDelete'])->name('exchange_rates.bulk_delete');
        Route::post('exchange_rates/bulk_set_active', [ExchangeRateController::class, 'bulkSetActive'])->name('exchange_rates.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:exchange_rates.delete')->group(function () {
        Route::post('exchange_rates/undo_last_delete', [ExchangeRateController::class, 'undoLastDelete'])->name('exchange_rates.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:exchange_rates.edit')->group(function () {
        Route::get('exchange_rates/edit_all',         [ExchangeRateController::class, 'editAll'])->name('exchange_rates.edit_all');
        Route::post('exchange_rates/edit_all/update', [ExchangeRateController::class, 'editAllUpdate'])->name('exchange_rates.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:exchange_rates.create')->group(function () {
        Route::get('exchange_rates/create', [ExchangeRateController::class, 'create'])->name('exchange_rates.create');
        Route::post('exchange_rates',       [ExchangeRateController::class, 'store'])->name('exchange_rates.store');
        Route::post('exchange_rates/{exchange_rate}/duplicate', [ExchangeRateController::class, 'duplicate'])->name('exchange_rates.duplicate');
    });

    Route::middleware('permission:exchange_rates.view')->group(function () {
        Route::get('exchange_rates',                 [ExchangeRateController::class, 'index'])->name('exchange_rates.index');
        Route::get('exchange_rates/{exchange_rate}', [ExchangeRateController::class, 'show'])->name('exchange_rates.show');
    });
    Route::middleware('permission:exchange_rates.edit')->group(function () {
        Route::get('exchange_rates/{exchange_rate}/edit', [ExchangeRateController::class, 'edit'])->name('exchange_rates.edit');
        Route::put('exchange_rates/{exchange_rate}',      [ExchangeRateController::class, 'update'])->name('exchange_rates.update');
    });
    Route::middleware('permission:exchange_rates.delete')->group(function () {
        Route::get('exchange_rates/{exchange_rate}/delete',        [ExchangeRateController::class, 'delete'])->name('exchange_rates.delete');
        Route::delete('exchange_rates/{exchange_rate}/deleteSave', [ExchangeRateController::class, 'deleteSave'])->name('exchange_rates.deleteSave');
    });

    // ── Discounts ──
    // Tier 1 parity con Customer/Warehouse master template.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('discounts/trash',                  [DiscountController::class, 'trash'])->name('discounts.trash');
        Route::post('discounts/bulk_restore',          [DiscountController::class, 'bulkRestore'])->name('discounts.bulk_restore');
        Route::post('discounts/{slug}/restore',        [DiscountController::class, 'restore'])->name('discounts.restore');
        Route::get('discounts/{slug}/restore',         fn () => redirect()->route('business_management.discounts.trash'));
        Route::delete('discounts/{slug}/force_delete', [DiscountController::class, 'forceDelete'])->name('discounts.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:discounts.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('discounts/export_excel', [DiscountController::class, 'exportExcel'])->name('discounts.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('discounts/export_pdf',   [DiscountController::class, 'exportPdf'])->name('discounts.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('discounts/export_word',  [DiscountController::class, 'exportWord'])->name('discounts.export_word');
        Route::middleware('throttle:5,1')
            ->post('discounts/export_csv',   [DiscountController::class, 'exportCsv'])->name('discounts.export_csv');
    });

    // 3) Imports (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:discounts.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('discounts/import',          [DiscountController::class, 'import'])->name('discounts.import');
        Route::get('discounts/import_template',  [DiscountController::class, 'importTemplate'])->name('discounts.import_template');
    });

    // 4) Bulk operations (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:discounts.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('discounts/bulk_delete',     [DiscountController::class, 'bulkDelete'])->name('discounts.bulk_delete');
        Route::post('discounts/bulk_set_active', [DiscountController::class, 'bulkSetActive'])->name('discounts.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:discounts.delete')->group(function () {
        Route::post('discounts/undo_last_delete', [DiscountController::class, 'undoLastDelete'])->name('discounts.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:discounts.edit')->group(function () {
        Route::get('discounts/edit_all',         [DiscountController::class, 'editAll'])->name('discounts.edit_all');
        Route::post('discounts/edit_all/update', [DiscountController::class, 'editAllUpdate'])->name('discounts.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:discounts.create')->group(function () {
        Route::get('discounts/create', [DiscountController::class, 'create'])->name('discounts.create');
        Route::post('discounts',       [DiscountController::class, 'store'])->name('discounts.store');
        Route::post('discounts/{discount}/duplicate', [DiscountController::class, 'duplicate'])->name('discounts.duplicate');
    });

    Route::middleware('permission:discounts.view')->group(function () {
        Route::get('discounts',            [DiscountController::class, 'index'])->name('discounts.index');
        Route::get('discounts/{discount}', [DiscountController::class, 'show'])->name('discounts.show');
    });
    Route::middleware('permission:discounts.edit')->group(function () {
        Route::get('discounts/{discount}/edit', [DiscountController::class, 'edit'])->name('discounts.edit');
        Route::put('discounts/{discount}',      [DiscountController::class, 'update'])->name('discounts.update');
    });
    Route::middleware('permission:discounts.delete')->group(function () {
        Route::get('discounts/{discount}/delete',        [DiscountController::class, 'delete'])->name('discounts.delete');
        Route::delete('discounts/{discount}/deleteSave', [DiscountController::class, 'deleteSave'])->name('discounts.deleteSave');
    });

    // ── Price Lists ──
    // Tier 1 parity con Customer/Discount master template.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('price_lists/trash',                  [PriceListController::class, 'trash'])->name('price_lists.trash');
        Route::post('price_lists/bulk_restore',          [PriceListController::class, 'bulkRestore'])->name('price_lists.bulk_restore');
        Route::post('price_lists/{slug}/restore',        [PriceListController::class, 'restore'])->name('price_lists.restore');
        Route::get('price_lists/{slug}/restore',         fn () => redirect()->route('business_management.price_lists.trash'));
        Route::delete('price_lists/{slug}/force_delete', [PriceListController::class, 'forceDelete'])->name('price_lists.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:price_lists.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('price_lists/export_excel', [PriceListController::class, 'exportExcel'])->name('price_lists.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('price_lists/export_pdf',   [PriceListController::class, 'exportPdf'])->name('price_lists.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('price_lists/export_word',  [PriceListController::class, 'exportWord'])->name('price_lists.export_word');
        Route::middleware('throttle:5,1')
            ->post('price_lists/export_csv',   [PriceListController::class, 'exportCsv'])->name('price_lists.export_csv');
    });

    // 3) Imports (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:price_lists.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('price_lists/import',          [PriceListController::class, 'import'])->name('price_lists.import');
        Route::get('price_lists/import_template',  [PriceListController::class, 'importTemplate'])->name('price_lists.import_template');
    });

    // 4) Bulk operations (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:price_lists.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('price_lists/bulk_delete',     [PriceListController::class, 'bulkDelete'])->name('price_lists.bulk_delete');
        Route::post('price_lists/bulk_set_active', [PriceListController::class, 'bulkSetActive'])->name('price_lists.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:price_lists.delete')->group(function () {
        Route::post('price_lists/undo_last_delete', [PriceListController::class, 'undoLastDelete'])->name('price_lists.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:price_lists.edit')->group(function () {
        Route::get('price_lists/edit_all',         [PriceListController::class, 'editAll'])->name('price_lists.edit_all');
        Route::post('price_lists/edit_all/update', [PriceListController::class, 'editAllUpdate'])->name('price_lists.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:price_lists.create')->group(function () {
        Route::get('price_lists/create', [PriceListController::class, 'create'])->name('price_lists.create');
        Route::post('price_lists',       [PriceListController::class, 'store'])->name('price_lists.store');
        Route::post('price_lists/{price_list}/duplicate', [PriceListController::class, 'duplicate'])->name('price_lists.duplicate');
    });

    Route::middleware('permission:price_lists.view')->group(function () {
        Route::get('price_lists',                [PriceListController::class, 'index'])->name('price_lists.index');
        Route::get('price_lists/{price_list}',   [PriceListController::class, 'show'])->name('price_lists.show');
    });
    Route::middleware('permission:price_lists.edit')->group(function () {
        Route::get('price_lists/{price_list}/edit', [PriceListController::class, 'edit'])->name('price_lists.edit');
        Route::put('price_lists/{price_list}',      [PriceListController::class, 'update'])->name('price_lists.update');
    });
    Route::middleware('permission:price_lists.delete')->group(function () {
        Route::get('price_lists/{price_list}/delete',        [PriceListController::class, 'delete'])->name('price_lists.delete');
        Route::delete('price_lists/{price_list}/deleteSave', [PriceListController::class, 'deleteSave'])->name('price_lists.deleteSave');
    });

    // ── Deliveries ── (Tier 1 parity con Customer master template)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('deliveries/trash',                  [DeliveryController::class, 'trash'])->name('deliveries.trash');
        Route::post('deliveries/bulk_restore',          [DeliveryController::class, 'bulkRestore'])->name('deliveries.bulk_restore');
        Route::post('deliveries/{slug}/restore',        [DeliveryController::class, 'restore'])->name('deliveries.restore');
        Route::get('deliveries/{slug}/restore',         fn () => redirect()->route('business_management.deliveries.trash'));
        Route::delete('deliveries/{slug}/force_delete', [DeliveryController::class, 'forceDelete'])->name('deliveries.force_delete');
    });

    // 2) Exports (gated por plan_feature)
    Route::middleware('permission:deliveries.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('deliveries/export_excel', [DeliveryController::class, 'exportExcel'])->name('deliveries.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('deliveries/export_pdf',   [DeliveryController::class, 'exportPdf'])->name('deliveries.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('deliveries/export_word',  [DeliveryController::class, 'exportWord'])->name('deliveries.export_word');
        Route::middleware('throttle:5,1')
            ->post('deliveries/export_csv',   [DeliveryController::class, 'exportCsv'])->name('deliveries.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:deliveries.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('deliveries/import',          [DeliveryController::class, 'import'])->name('deliveries.import');
        Route::get('deliveries/import_template',  [DeliveryController::class, 'importTemplate'])->name('deliveries.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:deliveries.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('deliveries/bulk_delete',     [DeliveryController::class, 'bulkDelete'])->name('deliveries.bulk_delete');
        Route::post('deliveries/bulk_set_active', [DeliveryController::class, 'bulkSetActive'])->name('deliveries.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:deliveries.delete')->group(function () {
        Route::post('deliveries/undo_last_delete', [DeliveryController::class, 'undoLastDelete'])->name('deliveries.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:deliveries.edit')->group(function () {
        Route::get('deliveries/edit_all',         [DeliveryController::class, 'editAll'])->name('deliveries.edit_all');
        Route::post('deliveries/edit_all/update', [DeliveryController::class, 'editAllUpdate'])->name('deliveries.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:deliveries.create')->group(function () {
        Route::get('deliveries/create', [DeliveryController::class, 'create'])->name('deliveries.create');
        Route::post('deliveries',       [DeliveryController::class, 'store'])->name('deliveries.store');
        Route::post('deliveries/{delivery}/duplicate', [DeliveryController::class, 'duplicate'])->name('deliveries.duplicate');
    });

    Route::middleware('permission:deliveries.view')->group(function () {
        Route::get('deliveries',                                 [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/sales_order/{sales_order}/lines', [DeliveryController::class, 'getSalesOrderLines'])->name('deliveries.so_lines');
        Route::get('deliveries/{delivery}',                      [DeliveryController::class, 'show'])->name('deliveries.show');
    });
    Route::middleware('permission:deliveries.edit')->group(function () {
        Route::get('deliveries/{delivery}/edit', [DeliveryController::class, 'edit'])->name('deliveries.edit');
        Route::put('deliveries/{delivery}',      [DeliveryController::class, 'update'])->name('deliveries.update');
    });
    Route::middleware('permission:deliveries.delete')->group(function () {
        Route::get('deliveries/{delivery}/delete',        [DeliveryController::class, 'delete'])->name('deliveries.delete');
        Route::delete('deliveries/{delivery}/deleteSave', [DeliveryController::class, 'deleteSave'])->name('deliveries.deleteSave');
        Route::delete('deliveries/{delivery}',            [DeliveryController::class, 'destroy'])->name('deliveries.destroy');
    });

    // ── Stock Takes ── (Tier 1 parity con Customer master template)

    // 1) Trash + restore + force_delete (super only)
    Route::middleware('role:super')->group(function () {
        Route::get('stock_takes/trash',                  [StockTakeController::class, 'trash'])->name('stock_takes.trash');
        Route::post('stock_takes/bulk_restore',          [StockTakeController::class, 'bulkRestore'])->name('stock_takes.bulk_restore');
        Route::post('stock_takes/{slug}/restore',        [StockTakeController::class, 'restore'])->name('stock_takes.restore');
        Route::get('stock_takes/{slug}/restore',         fn () => redirect()->route('business_management.stock_takes.trash'));
        Route::delete('stock_takes/{slug}/force_delete', [StockTakeController::class, 'forceDelete'])->name('stock_takes.force_delete');
    });

    // 2) Exports (gated por plan_feature)
    Route::middleware('permission:stock_takes.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('stock_takes/export_excel', [StockTakeController::class, 'exportExcel'])->name('stock_takes.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('stock_takes/export_pdf',   [StockTakeController::class, 'exportPdf'])->name('stock_takes.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('stock_takes/export_word',  [StockTakeController::class, 'exportWord'])->name('stock_takes.export_word');
        Route::middleware('throttle:5,1')
            ->post('stock_takes/export_csv',   [StockTakeController::class, 'exportCsv'])->name('stock_takes.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:stock_takes.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('stock_takes/import',          [StockTakeController::class, 'import'])->name('stock_takes.import');
        Route::get('stock_takes/import_template',  [StockTakeController::class, 'importTemplate'])->name('stock_takes.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:stock_takes.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('stock_takes/bulk_delete',     [StockTakeController::class, 'bulkDelete'])->name('stock_takes.bulk_delete');
        Route::post('stock_takes/bulk_set_active', [StockTakeController::class, 'bulkSetActive'])->name('stock_takes.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:stock_takes.delete')->group(function () {
        Route::post('stock_takes/undo_last_delete', [StockTakeController::class, 'undoLastDelete'])->name('stock_takes.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:stock_takes.edit')->group(function () {
        Route::get('stock_takes/edit_all',         [StockTakeController::class, 'editAll'])->name('stock_takes.edit_all');
        Route::post('stock_takes/edit_all/update', [StockTakeController::class, 'editAllUpdate'])->name('stock_takes.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:stock_takes.create')->group(function () {
        Route::get('stock_takes/create', [StockTakeController::class, 'create'])->name('stock_takes.create');
        Route::post('stock_takes',       [StockTakeController::class, 'store'])->name('stock_takes.store');
        Route::post('stock_takes/{stock_take}/duplicate', [StockTakeController::class, 'duplicate'])->name('stock_takes.duplicate');
    });

    Route::middleware('permission:stock_takes.view')->group(function () {
        Route::get('stock_takes',                [StockTakeController::class, 'index'])->name('stock_takes.index');
        Route::get('stock_takes/{stock_take}',   [StockTakeController::class, 'show'])->name('stock_takes.show');
    });
    Route::middleware('permission:stock_takes.edit')->group(function () {
        Route::get('stock_takes/{stock_take}/edit', [StockTakeController::class, 'edit'])->name('stock_takes.edit');
        Route::put('stock_takes/{stock_take}',      [StockTakeController::class, 'update'])->name('stock_takes.update');
    });
    Route::middleware('permission:stock_takes.delete')->group(function () {
        Route::get('stock_takes/{stock_take}/delete',        [StockTakeController::class, 'delete'])->name('stock_takes.delete');
        Route::delete('stock_takes/{stock_take}/deleteSave', [StockTakeController::class, 'deleteSave'])->name('stock_takes.deleteSave');
        Route::delete('stock_takes/{stock_take}',            [StockTakeController::class, 'destroy'])->name('stock_takes.destroy');
    });


    // ── Warehouses ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('warehouses/trash',                  [WarehouseController::class, 'trash'])->name('warehouses.trash');
        Route::post('warehouses/bulk_restore',          [WarehouseController::class, 'bulkRestore'])->name('warehouses.bulk_restore');
        Route::post('warehouses/{slug}/restore',        [WarehouseController::class, 'restore'])->name('warehouses.restore');
        Route::get('warehouses/{slug}/restore',         fn () => redirect()->route('business_management.warehouses.trash'));
        Route::delete('warehouses/{slug}/force_delete', [WarehouseController::class, 'forceDelete'])->name('warehouses.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:warehouses.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('warehouses/export_excel', [WarehouseController::class, 'exportExcel'])->name('warehouses.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('warehouses/export_pdf',   [WarehouseController::class, 'exportPdf'])->name('warehouses.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('warehouses/export_word',  [WarehouseController::class, 'exportWord'])->name('warehouses.export_word');
        Route::middleware('throttle:5,1')
            ->post('warehouses/export_csv',   [WarehouseController::class, 'exportCsv'])->name('warehouses.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:warehouses.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('warehouses/import',          [WarehouseController::class, 'import'])->name('warehouses.import');
        Route::get('warehouses/import_template',  [WarehouseController::class, 'importTemplate'])->name('warehouses.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:warehouses.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('warehouses/bulk_delete',     [WarehouseController::class, 'bulkDelete'])->name('warehouses.bulk_delete');
        Route::post('warehouses/bulk_set_active', [WarehouseController::class, 'bulkSetActive'])->name('warehouses.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:warehouses.delete')->group(function () {
        Route::post('warehouses/undo_last_delete', [WarehouseController::class, 'undoLastDelete'])->name('warehouses.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:warehouses.edit')->group(function () {
        Route::get('warehouses/edit_all',         [WarehouseController::class, 'editAll'])->name('warehouses.edit_all');
        Route::post('warehouses/edit_all/update', [WarehouseController::class, 'editAllUpdate'])->name('warehouses.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:warehouses.create')->group(function () {
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses',       [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::post('warehouses/{warehouse}/duplicate', [WarehouseController::class, 'duplicate'])->name('warehouses.duplicate');
    });

    Route::middleware('permission:warehouses.view')->group(function () {
        Route::get('warehouses',                [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/{warehouse}',  [WarehouseController::class, 'show'])->name('warehouses.show');
    });
    Route::middleware('permission:warehouses.edit')->group(function () {
        Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('warehouses/{warehouse}',      [WarehouseController::class, 'update'])->name('warehouses.update');
    });
    Route::middleware('permission:warehouses.delete')->group(function () {
        Route::get('warehouses/{warehouse}/delete',        [WarehouseController::class, 'delete'])->name('warehouses.delete');
        Route::delete('warehouses/{warehouse}/deleteSave', [WarehouseController::class, 'deleteSave'])->name('warehouses.deleteSave');
    });


    // ── TaxClasses ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('tax_classes/trash',                  [TaxClassController::class, 'trash'])->name('tax_classes.trash');
        Route::post('tax_classes/bulk_restore',          [TaxClassController::class, 'bulkRestore'])->name('tax_classes.bulk_restore');
        Route::post('tax_classes/{slug}/restore',        [TaxClassController::class, 'restore'])->name('tax_classes.restore');
        Route::get('tax_classes/{slug}/restore',         fn () => redirect()->route('business_management.tax_classes.trash'));
        Route::delete('tax_classes/{slug}/force_delete', [TaxClassController::class, 'forceDelete'])->name('tax_classes.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:tax_classes.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('tax_classes/export_excel', [TaxClassController::class, 'exportExcel'])->name('tax_classes.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('tax_classes/export_pdf',   [TaxClassController::class, 'exportPdf'])->name('tax_classes.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('tax_classes/export_word',  [TaxClassController::class, 'exportWord'])->name('tax_classes.export_word');
        Route::middleware('throttle:5,1')
            ->post('tax_classes/export_csv',   [TaxClassController::class, 'exportCsv'])->name('tax_classes.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:tax_classes.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('tax_classes/import',          [TaxClassController::class, 'import'])->name('tax_classes.import');
        Route::get('tax_classes/import_template',  [TaxClassController::class, 'importTemplate'])->name('tax_classes.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:tax_classes.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('tax_classes/bulk_delete',     [TaxClassController::class, 'bulkDelete'])->name('tax_classes.bulk_delete');
        Route::post('tax_classes/bulk_set_active', [TaxClassController::class, 'bulkSetActive'])->name('tax_classes.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:tax_classes.delete')->group(function () {
        Route::post('tax_classes/undo_last_delete', [TaxClassController::class, 'undoLastDelete'])->name('tax_classes.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:tax_classes.edit')->group(function () {
        Route::get('tax_classes/edit_all',         [TaxClassController::class, 'editAll'])->name('tax_classes.edit_all');
        Route::post('tax_classes/edit_all/update', [TaxClassController::class, 'editAllUpdate'])->name('tax_classes.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:tax_classes.create')->group(function () {
        Route::get('tax_classes/create', [TaxClassController::class, 'create'])->name('tax_classes.create');
        Route::post('tax_classes',       [TaxClassController::class, 'store'])->name('tax_classes.store');
        Route::post('tax_classes/{taxClass}/duplicate', [TaxClassController::class, 'duplicate'])->name('tax_classes.duplicate');
    });

    Route::middleware('permission:tax_classes.view')->group(function () {
        Route::get('tax_classes',                [TaxClassController::class, 'index'])->name('tax_classes.index');
        Route::get('tax_classes/{taxClass}',  [TaxClassController::class, 'show'])->name('tax_classes.show');
    });
    Route::middleware('permission:tax_classes.edit')->group(function () {
        Route::get('tax_classes/{taxClass}/edit', [TaxClassController::class, 'edit'])->name('tax_classes.edit');
        Route::put('tax_classes/{taxClass}',      [TaxClassController::class, 'update'])->name('tax_classes.update');
    });
    Route::middleware('permission:tax_classes.delete')->group(function () {
        Route::get('tax_classes/{taxClass}/delete',        [TaxClassController::class, 'delete'])->name('tax_classes.delete');
        Route::delete('tax_classes/{taxClass}/deleteSave', [TaxClassController::class, 'deleteSave'])->name('tax_classes.deleteSave');
    });
});
