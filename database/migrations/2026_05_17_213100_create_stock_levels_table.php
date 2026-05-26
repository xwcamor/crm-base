<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_levels — stock actual por (product/variant × warehouse).
 *
 * Una row por combinación. Las cantidades se manipulan via stock_movements
 * (append-only audit), pero acá guardamos el "current state" para query rápido.
 *
 * qty_on_hand    = stock físico real
 * qty_reserved   = comprometido (en sales orders no entregadas)
 * qty_available  = on_hand - reserved (lo que SE PUEDE vender)
 * qty_incoming   = en purchase_orders no recibidas (forecast positivo)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->decimal('qty_reserved', 18, 4)->default(0);
            $table->decimal('qty_incoming', 18, 4)->default(0);

            // Costo promedio ponderado (FIFO/average tracking).
            $table->decimal('average_cost', 18, 4)->nullable();

            $table->timestamp('last_movement_at')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'stock_levels_unique');
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
