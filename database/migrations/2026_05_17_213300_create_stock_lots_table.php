<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_lots — lotes / batch tracking para productos con vencimiento o serial.
 *
 * Ej: farmacia con lotes vencimiento, electrónica con números de serie,
 * alimentos perecederos con FEFO (First Expiry First Out).
 *
 * Opcional — solo aplica si product.track_lots = true (futuro flag).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');

            $table->string('lot_number', 100)->index();
            $table->string('serial_number', 100)->nullable()->index();

            $table->decimal('qty_initial', 18, 4)->default(0);   // qty recibida originalmente
            $table->decimal('qty_remaining', 18, 4)->default(0); // qty actual del lote

            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();

            $table->decimal('unit_cost', 18, 4)->nullable();

            $table->unsignedBigInteger('source_purchase_order_id')->nullable();
            $table->unsignedBigInteger('source_supplier_id')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();

            $table->index(['product_id', 'warehouse_id', 'qty_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
