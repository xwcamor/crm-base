<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sales_orders — orden de venta firmada (Quote aceptado).
 *
 * Workflow: pending → processing → partially_shipped → shipped → delivered
 *                                                                     → cancelled
 *
 * Cuando se shippea, genera deliveries + stock_movements tipo 'issue'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();   // 'OV-2026-0001'
            $table->string('external_reference', 100)->nullable();

            // Source
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->unsignedBigInteger('deal_id')->nullable();

            // Cliente
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id')->nullable();

            $table->string('status', 30)->default('pending')->index();
            // pending/processing/partially_shipped/shipped/delivered/cancelled/closed

            // Warehouse default (override per delivery).
            $table->unsignedBigInteger('warehouse_id')->nullable();

            // Fechas
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Monetario
            $table->string('currency_code', 3)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);

            // Payment
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('payment_status', 30)->default('unpaid')->index();
            // unpaid/partial/paid/overdue

            // Shipping address (snapshot al crear la orden — para que cambios
            // posteriores en company no afecten esta orden).
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            $table->text('notes')->nullable();
            $table->string('internal_notes', 2000)->nullable();

            $table->unsignedBigInteger('owner_id')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status', 'order_date']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index('deleted_at');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('sku', 60)->nullable();

            $table->decimal('quantity_ordered', 18, 4)->default(1);
            $table->decimal('quantity_fulfilled', 18, 4)->default(0);
            $table->decimal('quantity_cancelled', 18, 4)->default(0);

            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_class_id')->nullable();
            $table->decimal('tax_pct', 7, 4)->default(0);

            $table->decimal('line_subtotal', 18, 2)->default(0);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('tax_class_id')->references('id')->on('tax_classes')->nullOnDelete();

            $table->index(['sales_order_id', 'sort_order']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX sales_orders_tenant_reference_unique " .
                "ON sales_orders (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
