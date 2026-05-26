<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notes — anotaciones libres polimórficas para Company/Contact/Deal/Ticket.
 *
 * `body` es markdown (renderizado en frontend). `is_pinned` permite "anclar"
 * notas importantes al top del timeline.
 *
 * Las mentions (@user) se procesan en el observer al crear/editar y
 * generan `notifications` para los users mencionados. La tabla de mentions
 * separada (`note_mentions`) llega en Fase 5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->morphs('noteable');   // Company, Contact, Deal, Ticket

            $table->text('body');         // Markdown content

            $table->boolean('is_pinned')->default(false);

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_pinned');
            $table->index('deleted_at');
            $table->index(['noteable_type', 'noteable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
