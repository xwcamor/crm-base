<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * contacts — tabla base generada por make:module.
 * Agregá columnas custom del dominio acá.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();

            // ─── CRM contact fields ───────────────────────────────────────
            // Nombres separados
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('middle_name', 120)->nullable();
            $table->string('salutation', 20)->nullable();

            // Identidad profesional
            $table->string('job_title', 150)->nullable();
            $table->string('department', 120)->nullable();

            // Contacto primario
            $table->string('primary_email', 254)->nullable();
            $table->string('primary_phone', 30)->nullable();
            $table->string('mobile_phone', 30)->nullable();

            // Relacion CRM
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('reports_to_contact_id')->nullable();
            $table->boolean('is_primary_for_company')->default(false);

            // Clasificacion CRM
            $table->string('lifecycle_stage', 30)->default('lead');
            $table->string('lead_source', 60)->nullable();
            $table->string('rating', 10)->default('none');
            $table->smallInteger('score')->default(0);

            // Asignacion
            $table->unsignedBigInteger('owner_id')->nullable();

            // Preferencias
            $table->unsignedBigInteger('preferred_language_id')->nullable();
            $table->string('timezone', 60)->nullable();

            // Compliance / opt-in
            $table->boolean('email_opt_in')->default(true);
            $table->boolean('sms_opt_in')->default(true);
            $table->boolean('whatsapp_opt_in')->default(true);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->boolean('do_not_contact')->default(false);

            // Personal
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            // Social
            $table->string('linkedin_url', 255)->nullable();
            $table->string('twitter_handle', 60)->nullable();
            $table->string('photo_url', 500)->nullable();

            // Sync externo
            $table->string('external_id', 100)->nullable();

            // ─── Sales qualification (MEDDIC) ─────────────────────────────
            $table->string('nickname', 60)->nullable();
            $table->string('seniority_level', 30)->nullable();
            $table->string('decision_role', 30)->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->string('preferred_channel', 20)->nullable();

            // ─── Pro fields (assistant, marketing compliance, engagement) ──
            $table->string('assistant_name', 200)->nullable();
            $table->string('assistant_email', 254)->nullable();
            $table->string('assistant_phone', 30)->nullable();
            $table->timestamp('marketing_opt_in_at')->nullable();
            $table->string('marketing_opt_in_source', 120)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribed_reason', 255)->nullable();
            $table->timestamp('last_engagement_at')->nullable();
            $table->string('relationship_strength', 20)->default('cold');
            // ───────────────────────────────────────────────────────────────

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Audit + soft-delete (patrón master template).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FKs CRM
            $table->foreign('company_id', 'fk_contacts_company')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('reports_to_contact_id', 'fk_contacts_reports_to')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('owner_id', 'fk_contacts_owner')->references('id')->on('users')->nullOnDelete();
            $table->foreign('preferred_language_id', 'fk_contacts_language')->references('id')->on('languages')->nullOnDelete();

// Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['tenant_id', 'is_active', 'created_at'], 'idx_contacts_tenant_active_created');
            $table->index('created_at', 'idx_contacts_created_at');
            $table->index('updated_at', 'idx_contacts_updated_at');
            $table->index('deleted_at', 'idx_contacts_deleted_at');
            $table->index('created_by', 'idx_contacts_created_by');
            $table->index('is_active',  'idx_contacts_is_active');

            // CRM indexes
            $table->index(['tenant_id', 'company_id'],         'idx_contacts_tenant_company');
            $table->index(['tenant_id', 'lifecycle_stage'],    'idx_contacts_tenant_lifecycle');
            $table->index(['tenant_id', 'owner_id'],           'idx_contacts_tenant_owner');
            $table->index('primary_email',                     'idx_contacts_email');
            $table->index(['first_name', 'last_name'],         'idx_contacts_first_last');
            $table->index(['tenant_id', 'seniority_level'],    'idx_contacts_tenant_seniority');
            $table->index(['tenant_id', 'decision_role'],      'idx_contacts_tenant_decision_role');
            $table->index(['tenant_id', 'is_decision_maker'],  'idx_contacts_tenant_decision_maker');
            $table->index(['tenant_id', 'last_engagement_at'], 'idx_contacts_tenant_engagement');
            $table->index(['tenant_id', 'relationship_strength'], 'idx_contacts_tenant_strength');
        });

        // Partial unique unaccent + pattern_ops — solo Postgres.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            // Unique de name por tenant (NULL = sistema, sin colision con uno real).
            DB::statement(
                "CREATE UNIQUE INDEX contacts_tenant_name_unique_active " .
                "ON contacts (COALESCE(tenant_id, 0), unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_contacts_name_pattern ON contacts (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX contacts_tenant_name_unique_active " .
                "ON contacts (COALESCE(tenant_id, 0), LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
