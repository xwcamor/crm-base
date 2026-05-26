<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * price_list_items — overrides de precio per producto/variant en una price_list.
 *
 * Si un product NO tiene row aqui, el precio se calcula como:
 *   price = products.list_price × (1 - price_lists.global_discount_pct / 100)
 *
 * Si tiene row, se usa el `price` o `discount_pct` definido en el item.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_list_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();   // si es variant especifica

            // Override absoluto OR descuento porcentual (excluyentes).
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('discount_pct', 5, 2)->nullable();

            // Cantidad mínima para aplicar (volume pricing).
            $table->decimal('min_quantity', 18, 4)->default(0);

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            $table->index(['price_list_id', 'product_id']);
            $table->index(['price_list_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
