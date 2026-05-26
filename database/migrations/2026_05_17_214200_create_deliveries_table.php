<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deliveries — entregas físicas contra una sales_order (fulfillment).
 *
 * Una sales_order puede tener N deliveries parciales. Cada delivery genera
 * stock_movements tipo 'issue' que descuentan del warehouse.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('reference', 30)->index();   // 'ENT-2026-0001'
            $table->string('prefix', 16)->nullable();

            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->string('status', 20)->default('pending');
            // pending/picking/packed/shipped/delivered/returned

            // Fechas
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('signed_by_name', 200)->nullable();

            // Carrier / tracking
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 80)->nullable()->index();
            $table->string('shipping_method', 60)->nullable();
            $table->decimal('shipping_cost', 18, 2)->default(0);

            // Address snapshot
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('deleted_description', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();

            $table->index(['tenant_id', 'status', 'shipped_at']);
            $table->index('deleted_at');
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('sales_order_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('stock_lot_id')->nullable();

            $table->decimal('quantity', 18, 4);

            $table->timestamps();

            $table->foreign('delivery_id')->references('id')->on('deliveries')->cascadeOnDelete();
            $table->foreign('sales_order_item_id')->references('id')->on('sales_order_items')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('stock_lot_id')->references('id')->on('stock_lots')->nullOnDelete();

            $table->index('delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
