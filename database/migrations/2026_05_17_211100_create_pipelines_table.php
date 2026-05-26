<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pipelines — múltiples pipelines per-tenant.
 * Ej: "Sales Pipeline", "Renewal Pipeline", "Onboarding Pipeline".
 *
 * Cada pipeline tiene sus propios stages (kanban columns).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 120)->index();
            $table->string('description', 500)->nullable();
            $table->string('color', 16)->default('#1677ff');

            // Pipeline default del workspace (uno por tenant).
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name'], 'pipelines_tenant_name_unique');
            $table->index(['tenant_id', 'is_default']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
