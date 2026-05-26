<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * companies — tabla base generada por make:module.
 * Agregá columnas custom del dominio acá.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();

            // ─── CRM-specific fields ───────────────────────────────────────
            // Razón social legal completa ("Acme S.A. de C.V."). El `name`
            // queda como nombre comercial/dba.
            $table->string('legal_name', 200)->nullable();

            // Tax ID (RUC/RFC/CUIT/EIN/NIT/VAT). Único por tenant via
            // partial unique index (más abajo).
            $table->string('tax_id', 50)->nullable()->index();

            // Tipo de relación: customer / supplier / both / prospect / partner.
            // Patrón Salesforce Account model — una sola entidad para B2B.
            $table->string('company_type', 20)->default('prospect')->index();

            // Lifecycle stage (HubSpot model): subscriber / lead / mql / sql /
            // opportunity / customer / evangelist / other.
            $table->string('lifecycle_stage', 30)->default('lead')->index();

            // FKs referenciales (todas nullable para no bloquear creación).
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('industry_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();              // sales rep asignado
            $table->unsignedBigInteger('parent_company_id')->nullable();     // jerarquía matriz/subsidiaria

            // Datos firmográficos opcionales.
            $table->string('website', 255)->nullable();
            $table->decimal('annual_revenue', 18, 2)->nullable();
            $table->unsignedInteger('employee_count')->nullable();

            // External_id para sync con Xero/Stripe/QuickBooks/etc.
            $table->string('external_id', 100)->nullable()->index();

            // Auto-numbering: prefix + reference correlativo ("COMP-2026-0001").
            // El counter lo maneja la app, no la BD.
            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();

            // ─── CRM extendido (moneda, terminos, idioma, score, social) ──
            $table->string('preferred_currency_code', 3)->nullable();
            $table->smallInteger('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->unsignedBigInteger('preferred_language_id')->nullable();
            $table->string('billing_email', 254)->nullable();
            $table->smallInteger('founded_year')->nullable();
            $table->string('rating', 10)->default('none');
            $table->smallInteger('score')->default(0);
            $table->string('tax_status', 30)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('twitter_handle', 60)->nullable();
            $table->string('instagram_url', 255)->nullable();

            // ─── Priority + post-venta ────────────────────────────────────
            $table->string('domain', 120)->nullable();
            $table->boolean('is_vip')->default(false);
            $table->string('priority', 15)->default('medium');
            $table->date('customer_since')->nullable();
            $table->unsignedBigInteger('account_manager_id')->nullable();

            // ─── Pro fields (fiscal/billing, health, attribution) ─────────
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exempt_reason', 255)->nullable();
            $table->string('legal_entity_type', 30)->nullable();
            $table->text('bank_account_info')->nullable();
            $table->decimal('discount_default_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('default_payment_method_id')->nullable();
            $table->string('account_status', 20)->default('active');
            $table->smallInteger('health_score')->nullable();
            $table->string('churn_risk', 10)->default('low');
            $table->unsignedBigInteger('referrer_company_id')->nullable();
            // ───────────────────────────────────────────────────────────────

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Audit + soft-delete (patrón master template).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ─── FKs (todas nullOnDelete: si borrás un country/industry/owner,
            //         la company NO se borra en cascada, solo se desreferencia)
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('industry_id')->references('id')->on('industries')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('preferred_language_id', 'fk_companies_language')->references('id')->on('languages')->nullOnDelete();
            $table->foreign('account_manager_id', 'fk_companies_account_manager')->references('id')->on('users')->nullOnDelete();
            $table->foreign('default_payment_method_id', 'fk_companies_payment_method')->references('id')->on('payment_methods')->nullOnDelete();
            $table->foreign('referrer_company_id', 'fk_companies_referrer')->references('id')->on('companies')->nullOnDelete();

            // Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['tenant_id', 'is_active', 'created_at'], 'idx_companies_tenant_active_created');
            $table->index('created_at', 'idx_companies_created_at');
            $table->index('updated_at', 'idx_companies_updated_at');
            $table->index('deleted_at', 'idx_companies_deleted_at');
            $table->index('created_by', 'idx_companies_created_by');
            $table->index('is_active',  'idx_companies_is_active');

            // CRM-specific indexes para filtros frecuentes.
            $table->index(['tenant_id', 'lifecycle_stage'], 'idx_companies_tenant_lifecycle');
            $table->index(['tenant_id', 'company_type'],    'idx_companies_tenant_type');
            $table->index(['tenant_id', 'owner_id'],        'idx_companies_tenant_owner');
            $table->index(['tenant_id', 'industry_id'],     'idx_companies_tenant_industry');

            // CRM extendido + priority + pro
            $table->index(['tenant_id', 'rating'],                       'idx_companies_tenant_rating');
            $table->index(['tenant_id', 'preferred_currency_code'],      'idx_companies_tenant_currency');
            $table->index('score',                                       'idx_companies_score');
            $table->index('domain',                                      'idx_companies_domain');
            $table->index(['tenant_id', 'priority'],                     'idx_companies_tenant_priority');
            $table->index(['tenant_id', 'is_vip'],                       'idx_companies_tenant_vip');
            $table->index(['tenant_id', 'account_manager_id'],           'idx_companies_account_manager');
            $table->index(['tenant_id', 'account_status', 'churn_risk'], 'idx_companies_tenant_health');
        });

        $driver = DB::getDriverName();

        // Partial unique de tax_id por tenant (solo si tax_id no es null).
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX companies_tenant_tax_id_unique " .
                "ON companies (COALESCE(tenant_id, 0), tax_id) " .
                "WHERE tax_id IS NOT NULL AND deleted_at IS NULL"
            );
        }

        // Partial unique de reference por tenant (auto-numbering correlativo).
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX companies_tenant_reference_unique " .
                "ON companies (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }

        // Partial unique unaccent + pattern_ops — solo Postgres.
        if ($driver === 'pgsql') {
            // Unique de name por tenant (NULL = sistema, sin colision con uno real).
            DB::statement(
                "CREATE UNIQUE INDEX companies_tenant_name_unique_active " .
                "ON companies (COALESCE(tenant_id, 0), unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_companies_name_pattern ON companies (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX companies_tenant_name_unique_active " .
                "ON companies (COALESCE(tenant_id, 0), LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
