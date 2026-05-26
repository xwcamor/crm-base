<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_competitors — competidores presentes en un deal.
 *
 * Cada deal puede tener N competidores compitiendo. Util para:
 *   - Reports: "vs Competitor X, win rate = 35%"
 *   - Playbooks: "si compete con Salesforce, usar template Y"
 *   - Loss analysis: "perdimos 60% de deals donde estaba Competitor Z"
 *
 * competitor_company_id apunta a una company en companies con type='competitor'
 * (extiende el patron unificado de companies para suppliers/customers/etc).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_competitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('competitor_company_id')->nullable();
            $table->string('competitor_name', 200);   // fallback si no esta en companies
            $table->string('status', 30)->default('active');   // active/dropped/won/lost
            $table->string('strengths', 500)->nullable();
            $table->string('weaknesses', 500)->nullable();
            $table->string('notes', 1000)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
            $table->foreign('competitor_company_id')->references('id')->on('companies')->nullOnDelete();

            $table->index('deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_competitors');
    }
};
