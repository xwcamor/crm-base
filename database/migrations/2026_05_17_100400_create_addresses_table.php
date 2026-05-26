<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * addresses — direcciones polimórficas para Company/Contact/Quote/Invoice/etc.
 *
 * Patrón Laravel CRM: en vez de columnas address_* en Contact y Company,
 * tenemos una tabla polimórfica que permite N direcciones por entidad
 * (work + home, billing + shipping, etc.).
 *
 * El `type` distingue uso: 'main' (default), 'billing', 'shipping', 'work', 'home'.
 * `is_primary` marca la principal de su tipo (un Contact puede tener 2 'work'
 * pero solo 1 puede ser primary).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Owner polimórfico: Company, Contact, Quote, Invoice, etc.
            $table->morphs('addressable');   // addressable_type + addressable_id + idx

            $table->string('type', 30)->default('main')->index();
            $table->string('label', 80)->nullable();   // "Oficina central", "Casa", etc.

            $table->string('street_line1', 200);
            $table->string('street_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();

            // Geolocalización opcional (para mapas, distancias).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_primary')->default(false);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
