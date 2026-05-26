<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * currencies — catálogo global de monedas ISO 4217.
 *
 * Global (sin tenant_id): todas las empresas comparten el mismo catálogo de
 * monedas. Si un tenant quiere desactivar monedas que no usa, lo hace via
 * is_active y se filtra en la UI de selectores de moneda.
 *
 * Las tasas de cambio están en `exchange_rates` (history table).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // ISO 4217: USD, EUR, ARS, PEN, MXN, etc.
            $table->string('code', 3)->unique();

            $table->string('name', 80);
            $table->string('symbol', 8);           // $, €, £, ¥

            // Decimales por defecto (USD=2, JPY=0, BHD=3, etc.)
            $table->unsignedTinyInteger('decimal_places')->default(2);

            $table->boolean('is_active')->default(true);

            // Audit (patron master template — sin BelongsToTenant porque es global).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
