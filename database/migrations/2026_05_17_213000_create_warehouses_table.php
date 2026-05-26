<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * warehouses — depósitos / locales / sucursales del tenant.
 * El stock_levels rastrea qty por (product × warehouse).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 30)->index();   // 'WH01', 'BSAS-CENTRAL'
            $table->string('name', 150)->index();
            $table->string('description', 500)->nullable();

            // Dirección (opcional — alternativa: address polymorphic).
            $table->string('address_line', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();

            // Tipo: main / branch / dropship / virtual / consignment
            $table->string('type', 30)->default('main');

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('manager_user_id')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('manager_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'warehouses_tenant_code_unique');
            $table->index(['tenant_id', 'is_default']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
