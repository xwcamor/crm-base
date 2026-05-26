<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * payment_methods + payments — métodos y movimientos de cobro/pago.
 *
 * Un payment cubre 1 invoice (no partial multi-invoice). amount_paid del
 * invoice se actualiza al postear el payment, y status cambia a 'paid' si
 * balance_due = 0.
 */
return new class extends Migration {
    public function up(): void
    {
        // Catálogo de métodos de pago (per-tenant).
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 100)->index();   // 'Transferencia', 'Stripe', 'Cash', 'MercadoPago'
            $table->string('code', 30)->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->string('integration_provider', 60)->nullable();   // 'stripe', 'mercadopago', etc.
            $table->boolean('requires_reference')->default(false);   // ej: transferencia requiere nro
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name'], 'payment_methods_tenant_name_unique');
            $table->index('deleted_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('reference', 30)->nullable()->index();   // PAGO-2026-0001

            // ¿De qué cliente? (denormalizado para query rápido)
            $table->unsignedBigInteger('company_id')->nullable();

            // Aplicación: a un invoice especifico.
            $table->unsignedBigInteger('invoice_id')->nullable();

            // Si no hay invoice — adelanto (credit memo, deposit, retainer).
            $table->string('type', 30)->default('invoice_payment');
            // invoice_payment / deposit / credit_memo / refund

            $table->unsignedBigInteger('payment_method_id');

            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('amount_in_invoice_currency', 18, 2)->nullable();   // converted

            $table->timestamp('paid_at');
            $table->timestamp('reconciled_at')->nullable();

            $table->string('external_transaction_id', 100)->nullable()->index();
            $table->string('bank_reference', 100)->nullable();   // nro de transferencia
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('completed');
            // pending/completed/failed/refunded/disputed
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();

            $table->index(['tenant_id', 'status', 'paid_at']);
            $table->index(['tenant_id', 'invoice_id']);
            $table->index('deleted_at');
        });

        // Partial unique de reference por tenant (auto-numbering PAGO-YYYY-NNNNN).
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX payments_tenant_reference_unique " .
                "ON payments (COALESCE(tenant_id, 0), reference) " .
                "WHERE reference IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
