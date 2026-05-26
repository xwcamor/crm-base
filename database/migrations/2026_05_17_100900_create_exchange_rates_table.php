<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exchange_rates — history de tasas de cambio entre monedas.
 *
 * Append-only: cada vez que se importa una tasa (manual o via API tipo
 * Fixer.io / ExchangeRate-API), se inserta una row. Para "rate actual"
 * el query es: SELECT rate FROM exchange_rates WHERE base_code = ? AND
 * quote_code = ? ORDER BY valid_at DESC LIMIT 1.
 *
 * Global (sin tenant_id): las tasas son universales. Si un tenant quiere
 * tasas custom (negociaciones internas), se maneja en application layer
 * con override per-tenant — futura feature.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            // ISO 4217 codes — FK lógico contra currencies.code (no FK física
            // para evitar cascade weirdness con history).
            $table->string('base_code', 3);
            $table->string('quote_code', 3);

            // Tasa con 6 decimales (suficiente para crypto + fiat).
            $table->decimal('rate', 18, 6);

            $table->timestamp('valid_at')->index();   // Cuando aplica esta tasa

            // Fuente: 'manual', 'fixer.io', 'exchangerate-api', 'bcra', etc.
            $table->string('source', 60)->default('manual');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Query principal: lookup actual rate para un par de monedas.
            $table->index(['base_code', 'quote_code', 'valid_at'], 'idx_rates_pair_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
