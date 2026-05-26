<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_movements — audit append-only de TODOS los cambios de stock.
 *
 * Cada movement modifica stock_levels.qty_on_hand en delta.
 *
 * `type`:
 *   - receipt      = entrada (de purchase_order)
 *   - issue        = salida (a sales_order delivery)
 *   - transfer_in  = transferencia entrante (otro warehouse)
 *   - transfer_out = transferencia saliente
 *   - adjustment   = ajuste manual (merma, robo, conteo físico)
 *   - return_in    = devolución de cliente
 *   - return_out   = devolución a proveedor
 *
 * `source_type/source_id` polimórfico → apunta al doc origen (Order/Delivery/PO/etc).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->string('type', 30);                   // receipt/issue/transfer_in/out/adjustment/return_*
            $table->decimal('quantity', 18, 4);            // siempre POSITIVA (el type indica dirección)
            $table->decimal('unit_cost', 18, 4)->nullable();   // costo del movimiento (para promedio)
            $table->decimal('total_cost', 18, 4)->nullable();

            // Polimórfico hacia el doc origen.
            $table->string('source_type', 80)->nullable();    // App\Models\PurchaseOrder, App\Models\Delivery, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_reference', 60)->nullable();   // "PO-2026-0001"

            // Lot tracking (opcional).
            $table->unsignedBigInteger('stock_lot_id')->nullable();

            $table->string('note', 500)->nullable();
            $table->timestamp('moved_at');

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->index(['warehouse_id', 'product_id', 'moved_at']);
            $table->index(['source_type', 'source_id'], 'idx_stock_mov_source');
            $table->index(['tenant_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
