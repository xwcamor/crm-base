<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * discounts — vouchers / promo codes / cupones.
 *
 * Aplicable a quotes/orders/invoices. Tracking de redenciones en
 * discount_redemptions (cuántas veces se usó, por quién, en qué transacción).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 60)->index();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();

            // type: percentage / fixed_amount / free_shipping
            $table->string('type', 20)->default('percentage');
            $table->decimal('value', 18, 4)->default(0);   // 25 (= 25% si percentage) o 100.00 (si fixed)
            $table->string('currency_code', 3)->nullable();   // solo si fixed_amount

            // Restricciones
            $table->decimal('min_purchase_amount', 18, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();        // total redenciones
            $table->unsignedInteger('usage_per_customer')->nullable(); // per customer
            $table->unsignedInteger('usage_count')->default(0);

            // Vigencia
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'discounts_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
