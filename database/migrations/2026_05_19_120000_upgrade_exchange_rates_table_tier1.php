<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exchange_rates upgrade to Tier 1 parity (Discount/Customer master template).
 *
 * Agrega: slug, is_active, tenant_id (nullable + index), deleted_by,
 * deleted_description, soft deletes. Cambia el unique key compuesto a
 * (tenant_id, base_code, quote_code, valid_at) para soportar per-tenant.
 *
 * Mantiene `created_by` y la columna existente `source` como informacional.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->string('slug', 22)->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('source');

            $table->unsignedBigInteger('tenant_id')->nullable()->after('created_by');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('tenant_id');
            $table->text('deleted_description')->nullable()->after('deleted_by');

            $table->softDeletes();

            $table->index('tenant_id', 'exchange_rates_tenant_idx');
            $table->index(['tenant_id', 'is_active'], 'exchange_rates_tenant_active_idx');
            $table->index('deleted_at', 'exchange_rates_deleted_at_idx');
        });

        // Backfill slug for existing rows (random 22 chars).
        $rows = \DB::table('exchange_rates')->whereNull('slug')->get(['id']);
        foreach ($rows as $row) {
            \DB::table('exchange_rates')->where('id', $row->id)->update([
                'slug' => \Illuminate\Support\Str::random(22),
            ]);
        }

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->string('slug', 22)->nullable(false)->change();
            $table->unique('slug', 'exchange_rates_slug_unique');
            $table->unique(
                ['tenant_id', 'base_code', 'quote_code', 'valid_at'],
                'exchange_rates_tenant_pair_valid_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropUnique('exchange_rates_tenant_pair_valid_unique');
            $table->dropUnique('exchange_rates_slug_unique');
            $table->dropIndex('exchange_rates_deleted_at_idx');
            $table->dropIndex('exchange_rates_tenant_active_idx');
            $table->dropIndex('exchange_rates_tenant_idx');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'slug', 'is_active', 'tenant_id', 'deleted_by', 'deleted_description',
            ]);
        });
    }
};
