<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * customers — tabla base generada por make:module.
 * Agregá columnas custom del dominio acá.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            // @scaffold:anchor description-column

            // @scaffold:remove-begin commercial-fields
            // `cod` representa el identificador comercial del cliente: RUC,
            // RFC, CUIT, código interno, etc. Genérico para cualquier país.
            $table->string('cod', 50)->nullable()->index();

            // País del cliente. FK con nullOnDelete: si se borra el country,
            // el customer queda sin país pero no se borra en cascada.
            $table->unsignedBigInteger('country_id')->nullable();
            // @scaffold:remove-end

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Audit + soft-delete (patrón master template).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // @scaffold:remove-begin commercial-fk-unique
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->nullOnDelete();

            // Unicidad de cod dentro de cada tenant.
            $table->unique(['tenant_id', 'cod'], 'customers_tenant_cod_unique');
            // @scaffold:remove-end

            // Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['tenant_id', 'is_active', 'created_at'], 'idx_customers_tenant_active_created');
            $table->index('created_at', 'idx_customers_created_at');
            $table->index('updated_at', 'idx_customers_updated_at');
            $table->index('deleted_at', 'idx_customers_deleted_at');
            $table->index('created_by', 'idx_customers_created_by');
            $table->index('is_active',  'idx_customers_is_active');
        });

        // Partial unique unaccent + pattern_ops — solo Postgres.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            // Unique de name por tenant (NULL = sistema, sin colision con uno real).
            DB::statement(
                "CREATE UNIQUE INDEX customers_tenant_name_unique_active " .
                "ON customers (COALESCE(tenant_id, 0), unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_customers_name_pattern ON customers (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX customers_tenant_name_unique_active " .
                "ON customers (COALESCE(tenant_id, 0), LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
