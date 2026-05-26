<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pipeline_stages — columnas del kanban dentro de un pipeline.
 * Ej: "Prospecting → Qualified → Proposal → Negotiation → Closed Won/Lost".
 *
 * `probability_pct` (0-100) define la probabilidad de cierre estimada por
 * stage — usado para weighted forecast: forecast = Σ deal.value × stage.prob.
 *
 * `is_won` / `is_lost` marcan stages terminales (no rota deal, no entra en
 * forecast del próximo período).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->unsignedBigInteger('pipeline_id');
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('color', 16)->default('#888888');

            // Posición en el kanban (drag-and-drop reordering).
            $table->unsignedInteger('sort_order')->default(0);

            // Probabilidad ponderada (0-100) para forecast.
            $table->unsignedTinyInteger('probability_pct')->default(0);

            // Stages terminales: won (positivo) o lost (negativo).
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);

            // Alerta si un deal queda en este stage más de N días (rotting).
            $table->unsignedInteger('rot_days')->default(0);   // 0 = sin alerta

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pipeline_id')->references('id')->on('pipelines')->cascadeOnDelete();
            $table->index(['pipeline_id', 'sort_order']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
