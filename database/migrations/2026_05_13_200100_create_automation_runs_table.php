<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * automation_runs — historial inmutable de ejecuciones.
 *
 * Cada vez que automations:tick dispara una automation, se crea una fila.
 * Status: running → success | failed. Si falla, error_message tiene el detalle.
 *
 * Sin soft-delete: es un log append-only para debugging y auditoría. Se
 * limpia con purge programado (configurable en config/purge.php).
 *
 * tenant_id está desnormalizado para facilitar el scoping cuando consultas
 * runs sin hacer JOIN con automations.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automation_id')->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running'); // running | success | failed

            $table->unsignedInteger('records_matched')->nullable();
            $table->text('output_summary')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->foreign('automation_id')
                ->references('id')->on('automations')
                ->onDelete('cascade');

            // Index para listar runs recientes por automation.
            $table->index(['automation_id', 'started_at'], 'idx_runs_auto_started');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
