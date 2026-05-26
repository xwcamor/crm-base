<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * deals — oportunidades de venta. El core del CRM.
 *
 * Cada deal vive en un pipeline + stage. value en una currency (puede
 * overridear el default de la company). expected_close_date alimenta el
 * forecast del período.
 *
 * `status`:
 *   - open   = deal activo en pipeline
 *   - won    = ganado (cerrado positivo)
 *   - lost   = perdido
 *   - dormant= pausado (no entra en forecast)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Identidad / numeración
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();

            // Pipeline + stage
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');

            // Status del deal
            $table->string('status', 20)->default('open')->index();   // open/won/lost/dormant

            // Valor monetario
            $table->decimal('value', 18, 2)->default(0);
            $table->string('currency_code', 3)->nullable();   // FK lógico a currencies.code
            $table->decimal('weighted_value', 18, 2)->default(0);   // value × stage.probability/100

            // Fechas
            $table->date('expected_close_date')->nullable()->index();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->unsignedBigInteger('lost_reason_source_id')->nullable();
            $table->string('lost_reason_note', 500)->nullable();

            // Relación
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();    // primary contact
            $table->unsignedBigInteger('owner_id')->nullable();      // sales rep
            $table->unsignedBigInteger('lead_source_id')->nullable();

            // Forecast helpers
            $table->unsignedTinyInteger('probability_pct')->nullable();   // override del stage.prob (0-100)

            // Sync externo
            $table->string('external_id', 100)->nullable()->index();

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FKs
            $table->foreign('pipeline_id')->references('id')->on('pipelines')->cascadeOnDelete();
            $table->foreign('stage_id')->references('id')->on('pipeline_stages')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('lead_source_id')->references('id')->on('lead_sources')->nullOnDelete();

            // Indexes para reports + kanban
            $table->index(['tenant_id', 'pipeline_id', 'stage_id'], 'idx_deals_tenant_pipe_stage');
            $table->index(['tenant_id', 'status', 'expected_close_date'], 'idx_deals_tenant_status_close');
            $table->index(['tenant_id', 'owner_id'], 'idx_deals_tenant_owner');
            $table->index(['tenant_id', 'company_id'], 'idx_deals_tenant_company');
            $table->index('deleted_at');
        });

        // Partial unique de reference por tenant.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX deals_tenant_reference_unique " .
                "ON deals (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
