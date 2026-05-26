<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_takes — conteos físicos de inventario.
 *
 * Workflow:
 *   1. User inicia un stock_take para un warehouse.
 *   2. Sistema genera lines con qty_system (qty actual según sistema).
 *   3. User cuenta físicamente, ingresa qty_counted.
 *   4. Al confirmar, se generan stock_movements tipo 'adjustment' por la
 *      diferencia (qty_counted - qty_system), corrigiendo el stock.
 */
return new class extends Migration {
    public function up(): void
    {
        // Header del conteo
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('reference', 30)->index();   // 'COUNT-2026-0001'

            $table->unsignedBigInteger('warehouse_id');

            $table->string('status', 20)->default('draft');   // draft / in_progress / completed / cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();

            $table->string('note', 1000)->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('deleted_description', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index('deleted_at');
        });

        // Detalle del conteo (una row por product/variant)
        Schema::create('stock_take_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_take_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->decimal('qty_system', 18, 4)->default(0);    // qty según sistema al iniciar
            $table->decimal('qty_counted', 18, 4)->nullable();   // qty contada físicamente
            $table->decimal('variance', 18, 4)->default(0);      // counted - system
            $table->string('note', 500)->nullable();

            $table->timestamps();

            $table->foreign('stock_take_id')->references('id')->on('stock_takes')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->index(['stock_take_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
        Schema::dropIfExists('stock_takes');
    }
};
