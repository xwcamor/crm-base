<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_products — line items tentativos en un deal (previo a Quote formal).
 *
 * Cuando el deal pasa a stage "Quote" / "Proposal", estos products se
 * convierten en quote_items via el QuoteBuilder. Mientras tanto, sirven
 * para calcular el deal.value automaticamente.
 *
 * Total = SUM(quantity × unit_price × (1 - discount_pct/100))
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');

            // product_id es nullable porque puede ser un servicio ad-hoc no
            // catalogado (con name + price escritos a mano).
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);  // 0-100
            $table->decimal('line_total', 18, 2)->default(0);   // computed = qty*price*(1-disc/100)

            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
            // No FK para product_id por ahora — products table se crea en Fase 4.
            // Se podra agregar via migration posterior.

            $table->index(['deal_id', 'sort_order']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_products');
    }
};
