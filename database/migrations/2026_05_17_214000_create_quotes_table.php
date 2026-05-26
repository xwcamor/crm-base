<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * quotes — cotizaciones formales enviadas al cliente.
 *
 * Workflow: draft → sent → accepted → expired
 *                                  → rejected
 *                                  → revised (genera quote_revisions snapshot)
 *
 * Cuando se acepta: convert → sales_order.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();   // 'COT-2026-0001'
            $table->string('external_reference', 100)->nullable();

            // Origen (opcional — un quote puede nacer suelto sin deal).
            $table->unsignedBigInteger('deal_id')->nullable();

            // Cliente
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();

            $table->string('status', 20)->default('draft')->index();
            // draft/sent/accepted/rejected/expired/revised
            $table->boolean('is_active')->default(true);

            // Fechas
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_reason', 500)->nullable();
            $table->string('signed_by_name', 200)->nullable();
            $table->string('signed_by_email', 254)->nullable();
            $table->string('signed_by_ip', 45)->nullable();

            // Monetario
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 18, 6)->nullable();   // si tenant.currency != quote.currency
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);

            // Discount header-level (override líneas)
            $table->unsignedBigInteger('discount_id')->nullable();   // FK a discounts (cupón)

            // Texto editorial
            $table->text('terms_md')->nullable();
            $table->text('notes')->nullable();
            $table->string('internal_notes', 2000)->nullable();

            // Ownership
            $table->unsignedBigInteger('owner_id')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('discount_id')->references('id')->on('discounts')->nullOnDelete();

            $table->index(['tenant_id', 'status', 'issue_date']);
            $table->index(['tenant_id', 'company_id']);
            $table->index('deleted_at');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('sku', 60)->nullable();   // snapshot del SKU al momento de la cotización

            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_class_id')->nullable();
            $table->decimal('tax_pct', 7, 4)->default(0);

            $table->decimal('line_subtotal', 18, 2)->default(0);   // qty*price*(1-disc/100)
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);       // subtotal + tax

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('tax_class_id')->references('id')->on('tax_classes')->nullOnDelete();

            $table->index(['quote_id', 'sort_order']);
        });

        // Snapshot per "send" — historial de versiones del quote.
        Schema::create('quote_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedSmallInteger('revision_number');   // 1, 2, 3, ...
            $table->json('snapshot');   // serialización completa del quote + items en este instante
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
            $table->unique(['quote_id', 'revision_number']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX quotes_tenant_reference_unique " .
                "ON quotes (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_revisions');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
