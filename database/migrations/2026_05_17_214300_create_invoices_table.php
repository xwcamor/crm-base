<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * invoices — facturas emitidas al cliente.
 *
 * Workflow: draft → sent → paid (cuando payments cubre balance) → overdue (si due_date < hoy)
 *                                                                         → cancelled (con motivo)
 *
 * `number` es el número correlativo legal (auto-generado, único por tenant).
 * `reference` es el código interno (puede coincidir o no con number).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Numeración legal (auto-generado, único por tenant).
            $table->string('number', 40)->index();
            $table->string('prefix', 10)->nullable();
            $table->string('reference', 30)->nullable()->index();
            $table->string('external_reference', 100)->nullable();
            $table->string('document_type', 30)->nullable();   // 'A', 'B', 'C' (AR), 'Factura', 'Boleta' (PE)

            // Source
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();   // si vino de un cycle

            // Cliente
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id')->nullable();

            $table->string('status', 20)->default('draft')->index();
            // draft/sent/paid/partial/overdue/cancelled/refunded
            $table->boolean('is_active')->default(true);

            // Fechas
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            // Monetario
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);   // grand_total - amount_paid

            // Address snapshot fiscal
            $table->json('billing_address')->nullable();
            $table->string('billing_tax_id', 50)->nullable();
            $table->string('billing_legal_name', 200)->nullable();

            // Notas
            $table->text('notes')->nullable();
            $table->text('terms_md')->nullable();
            $table->string('internal_notes', 2000)->nullable();

            $table->unsignedBigInteger('owner_id')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status', 'due_date']);
            $table->index(['tenant_id', 'company_id']);
            $table->index('deleted_at');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('sales_order_item_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('sku', 60)->nullable();

            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_class_id')->nullable();
            $table->decimal('tax_pct', 7, 4)->default(0);

            $table->decimal('line_subtotal', 18, 2)->default(0);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('tax_class_id')->references('id')->on('tax_classes')->nullOnDelete();

            $table->index(['invoice_id', 'sort_order']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX invoices_tenant_number_unique " .
                "ON invoices (COALESCE(tenant_id, 0), number)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
