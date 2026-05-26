<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * price_lists — listas de precios per segmento.
 *
 * Ej: "Standard Retail", "Wholesale 30% off", "Enterprise Q4", "Distribuidores LATAM".
 *
 * Cada price_list tiene N price_list_items (precio override por producto).
 * Una company puede tener una price_list default asignada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 150)->index();
            $table->string('description', 500)->nullable();

            $table->string('currency_code', 3)->nullable();   // moneda de los items

            // Vigencia (opcional — para promos limitadas).
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // Descuento global aplicado si el item no tiene precio override.
            // Ej: 'Wholesale' tiene global_discount_pct = 30 → todos los products
            // se venden a list_price * 0.7 salvo items con override propio.
            $table->decimal('global_discount_pct', 5, 2)->default(0);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);   // orden de aplicación

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name'], 'price_lists_tenant_name_unique');
            $table->index(['tenant_id', 'is_default']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
