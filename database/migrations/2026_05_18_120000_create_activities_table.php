<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * activities — registro polimorfico de interacciones (notas, llamadas, emails,
 * reuniones, tareas) ligadas a Deal/Company/Contact.
 *
 * Es la columna vertebral del seguimiento ("tracking") del CRM. Sin esta tabla,
 * el sales rep no tiene donde anotar que paso con un deal.
 *
 * Tipos:
 *   - note    = nota libre
 *   - call    = llamada telefonica (con resultado)
 *   - email   = email (con asunto + cuerpo, puede pegarse texto del email enviado)
 *   - meeting = reunion (con fecha+hora + lugar/URL)
 *   - task    = tarea pendiente (con due_date + prioridad)
 *
 * Estado:
 *   - pendiente:   completed_at IS NULL
 *   - completada:  completed_at IS NOT NULL
 *
 * Polimorfismo: activitable_type/activitable_id apunta a Deal, Company o Contact.
 * En el Show de cada uno se muestra el timeline filtrado por esa entidad.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Tipo + contenido
            $table->string('type', 20)->index();           // note/call/email/meeting/task
            $table->string('subject', 200)->nullable();
            $table->text('body')->nullable();

            // Tiempo
            $table->timestamp('due_at')->nullable()->index();        // cuando "debe pasar" (meeting/task)
            $table->timestamp('completed_at')->nullable()->index();  // cuando se marca hecha

            // Especificos por tipo
            $table->string('outcome', 30)->nullable();      // call: answered/voicemail/no_answer/rejected
            $table->unsignedInteger('duration_min')->nullable(); // call/meeting: minutos
            $table->string('location', 500)->nullable();    // meeting: URL Zoom/Meet o direccion
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 200)->nullable();
            $table->string('priority', 10)->nullable();     // task: low/medium/high

            // Link opcional a un quote (cotizacion). Workflow: registrar envio
            // del Quote crea una Activity tipo email con related_quote_id seteado
            // y auto-attach del PDF. nullOnDelete: si borran el quote, la activity
            // sobrevive (solo pierde el link).
            $table->unsignedBigInteger('related_quote_id')->nullable();

            // Polimorfica
            $table->string('activitable_type', 255);
            $table->unsignedBigInteger('activitable_id');

            // Actor + tenant
            $table->unsignedBigInteger('actor_user_id')->nullable();  // quien la registro
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FKs
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('related_quote_id')->references('id')->on('quotes')->nullOnDelete();

            // Indexes para queries comunes
            $table->index(['activitable_type', 'activitable_id'], 'idx_activities_morph');
            $table->index(['tenant_id', 'actor_user_id', 'completed_at'], 'idx_activities_user_status');
            $table->index(['tenant_id', 'due_at', 'completed_at'], 'idx_activities_agenda');
            $table->index(['tenant_id', 'type'], 'idx_activities_type');
            $table->index(['tenant_id', 'related_quote_id'], 'idx_activities_quote');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
