<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * products — catálogo de productos/servicios del tenant.
 *
 * `type`:
 *   - good         = bien físico (con stock)
 *   - service      = servicio (sin stock)
 *   - subscription = suscripción recurrente
 *   - bundle       = combo de otros products
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Identidad
            $table->string('sku', 60)->nullable()->index();
            $table->string('barcode', 60)->nullable()->index();   // EAN/UPC
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->text('long_description')->nullable();

            // Clasificación
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('type', 20)->default('good')->index();   // good/service/subscription/bundle
            $table->string('brand', 100)->nullable();

            // Precios base (en currency del tenant; price_lists overridea per segmento)
            $table->decimal('cost', 18, 4)->nullable();          // costo de adquisición
            $table->decimal('final_cost', 18, 4)->nullable();    // landed cost (incluye flete/aduanas)
            $table->decimal('list_price', 18, 4)->default(0);    // PVP base
            $table->string('currency_code', 3)->nullable();

            // Tax
            $table->unsignedBigInteger('tax_class_id')->nullable();

            // Stock management (solo aplica si type='good')
            $table->boolean('track_inventory')->default(true);
            $table->unsignedInteger('low_stock_threshold')->default(0);

            // Subscription (solo aplica si type='subscription')
            $table->string('billing_cycle', 20)->nullable();   // monthly/yearly/quarterly
            $table->unsignedInteger('billing_period')->default(1);   // cada N ciclos

            // Dimensiones (para shipping)
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->decimal('length_cm', 12, 2)->nullable();
            $table->decimal('width_cm', 12, 2)->nullable();
            $table->decimal('height_cm', 12, 2)->nullable();

            $table->string('image_url', 500)->nullable();
            $table->string('external_id', 100)->nullable()->index();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->index(['tenant_id', 'is_active', 'type']);
            $table->index('deleted_at');
        });

        // SKU unique por tenant.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX products_tenant_sku_unique " .
                "ON products (COALESCE(tenant_id, 0), sku) " .
                "WHERE sku IS NOT NULL AND deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
