<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variants — variantes de un producto (talles, colores, capacidades).
 *
 * Cada variant tiene su SKU propio + stock propio. Ej:
 *   - Camisa Oxford → variants: ['S azul', 'S blanco', 'M azul', 'M blanco', ...]
 *
 * Los `attributes` se guardan como JSON: { "size": "M", "color": "azul" }
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->unsignedBigInteger('product_id');

            $table->string('sku', 60)->index();
            $table->string('name', 200);
            $table->string('barcode', 60)->nullable()->index();

            // Atributos como JSON (size/color/material/etc.)
            $table->json('attributes')->nullable();

            // Override de price/cost (si null, usa el del product padre).
            $table->decimal('cost', 18, 4)->nullable();
            $table->decimal('price', 18, 4)->nullable();

            // Stock dedicado (si product.track_inventory = true).
            $table->unsignedInteger('low_stock_threshold')->default(0);

            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['tenant_id', 'sku'], 'product_variants_tenant_sku_unique');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
