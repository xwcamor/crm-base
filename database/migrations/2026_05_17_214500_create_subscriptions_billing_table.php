<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscriptions_billing — suscripciones recurrentes (SaaS, mantenimiento, retainer).
 *
 * Distinto de la tabla existente `subscriptions` (que es el plan del tenant).
 * Esto es revenue recurring que TUS clientes pagan A TI.
 *
 * Cron mensual genera invoices nuevas según el billing_cycle de cada subscription.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('reference', 30)->nullable()->index();   // SUB-2026-0001

            // Cliente
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id')->nullable();

            // Producto facturado (puede ser type='subscription' o cualquiera)
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();

            // Plan / nombre (snapshot por si el product cambia después)
            $table->string('plan_name', 200);
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);

            // Ciclo de facturación
            $table->string('billing_cycle', 20)->default('monthly');
            // monthly / yearly / quarterly / weekly
            $table->unsignedSmallInteger('billing_period')->default(1);   // cada N ciclos (Ej: bi-anual = yearly x 2)

            // Vigencia
            $table->date('start_date');
            $table->date('end_date')->nullable();   // null = recurrente indefinido
            $table->date('next_renewal_date')->index();
            $table->date('last_invoiced_at')->nullable();

            $table->string('status', 20)->default('active')->index();
            // active/paused/cancelled/expired/trial

            // Trial period
            $table->date('trial_end_date')->nullable();

            // Auto-renovacion + reminder
            $table->boolean('auto_renew')->default(true);
            $table->unsignedSmallInteger('reminder_days_before')->default(7);

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            // Discount (si aplica un descuento permanente al ciclo)
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);

            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('discount_id')->references('id')->on('discounts')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status', 'next_renewal_date']);
            $table->index(['tenant_id', 'company_id']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
