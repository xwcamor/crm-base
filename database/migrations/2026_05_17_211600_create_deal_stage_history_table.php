<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_stage_history — audit append-only de cambios de stage en un deal.
 *
 * Cada vez que un deal cambia de stage_id, se inserta una row con:
 *   - from_stage_id, to_stage_id, changed_by, changed_at
 *   - days_in_previous_stage (para velocity reports)
 *
 * Reports basados en esto:
 *   - Velocity: tiempo promedio de Lead → Won.
 *   - Stuck deals: deals con >N días en mismo stage.
 *   - Conversion rate: % de deals que pasan de stage X a stage Y.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_stage_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('from_stage_id')->nullable();   // null = creación del deal
            $table->unsignedBigInteger('to_stage_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->unsignedInteger('days_in_previous_stage')->default(0);
            $table->string('note', 500)->nullable();

            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
            $table->foreign('from_stage_id')->references('id')->on('pipeline_stages')->nullOnDelete();
            $table->foreign('to_stage_id')->references('id')->on('pipeline_stages')->restrictOnDelete();

            $table->index(['deal_id', 'changed_at']);
            $table->index(['to_stage_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stage_history');
    }
};
