<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lead_sources — catálogo per-tenant de fuentes de leads.
 * Ej: "Web Form", "Referral", "Trade Show", "Cold Call", "Google Ads".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 120)->index();
            $table->string('description', 255)->nullable();
            $table->string('category', 60)->nullable();   // 'organic', 'paid', 'referral', 'event'
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name'], 'lead_sources_tenant_name_unique');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
