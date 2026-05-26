<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * purchase_receipts — recepciones físicas de mercadería contra una PO.
 *
 * Una PO puede tener N recepciones parciales. Cada receipt genera
 * stock_movements tipo 'receipt' que suman al stock del warehouse.
 *
 * Tracking de qty discrepancies: lo recibido puede diferir de lo ordenado
 * (faltantes, dañados, sobre-envío) — se documenta en variance_note.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('reference', 30)->index();   // 'GRN-2026-0001'

            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->timestamp('received_at');
            $table->string('supplier_invoice_number', 80)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 80)->nullable();

            $table->text('variance_note')->nullable();   // descripcion de discrepancias

            $table->string('status', 20)->default('completed');   // completed / disputed

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();   // quien recibió
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();

            $table->index(['tenant_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('stock_lot_id')->nullable();

            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_rejected', 18, 4)->default(0);   // dañado/no acepto
            $table->string('rejection_reason', 255)->nullable();

            $table->timestamps();

            $table->foreign('purchase_receipt_id')->references('id')->on('purchase_receipts')->cascadeOnDelete();
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('stock_lot_id')->references('id')->on('stock_lots')->nullOnDelete();

            $table->index('purchase_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
