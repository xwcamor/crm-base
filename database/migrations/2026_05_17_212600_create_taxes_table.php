<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * taxes — tasas impositivas per (tax_class × country/region).
 *
 * Ej:
 *   IVA Standard (tax_class_id=1) + country=AR → rate=21%
 *   IVA Standard (tax_class_id=1) + country=PE → rate=18%
 *   IVA Reducido (tax_class_id=2) + country=AR → rate=10.5%
 *   Exempt       (tax_class_id=3) + country=*  → rate=0%
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tax_class_id');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('name', 120);
            $table->decimal('rate', 7, 4)->default(0);   // 0-100, 4 decimales (21.5000)

            // Vigencia: para cambios futuros de tasa (Ej: IVA sube de 21 a 23 desde 2027).
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tax_class_id')->references('id')->on('tax_classes')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();

            $table->index(['tax_class_id', 'country_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
