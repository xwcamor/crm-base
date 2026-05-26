<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * purchase_orders — OCs (órdenes de compra) a proveedores.
 *
 * Workflow:
 *   draft → submitted → confirmed → partially_received → received → closed
 *                                                                  → cancelled
 *
 * Cada PO tiene N purchase_order_items (líneas con product + qty + cost).
 * Cuando se recibe (purchase_receipt), genera stock_movements tipo 'receipt'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();   // 'PO-2026-0001'
            $table->string('external_reference', 100)->nullable();   // nro del proveedor

            // El proveedor es una Company con company_type='supplier' o 'both'.
            $table->unsignedBigInteger('supplier_company_id');

            $table->string('status', 20)->default('draft')->index();
            // draft/submitted/confirmed/partially_received/received/closed/cancelled

            // Warehouse destino
            $table->unsignedBigInteger('warehouse_id');

            // Fechas
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Totales
            $table->string('currency_code', 3)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);

            // Términos
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('terms_md')->nullable();
            $table->string('delivery_type', 30)->nullable();   // pickup/courier/freight
            $table->text('notes')->nullable();

            // Ownership
            $table->unsignedBigInteger('owner_id')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status', 'order_date']);
            $table->index(['tenant_id', 'supplier_company_id']);
            $table->index('deleted_at');
        });

        // Líneas de la PO
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);

            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_class_id')->nullable();
            $table->decimal('tax_pct', 7, 4)->default(0);
            $table->decimal('line_subtotal', 18, 2)->default(0);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('tax_class_id')->references('id')->on('tax_classes')->nullOnDelete();

            $table->index(['purchase_order_id', 'sort_order']);
        });

        // Partial unique de reference por tenant.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX purchase_orders_tenant_reference_unique " .
                "ON purchase_orders (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
