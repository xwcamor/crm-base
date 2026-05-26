<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tags — catálogo de etiquetas reusables, per-tenant.
 *
 * Cada workspace tiene su propio set de tags. Se asocian a registros via
 * la tabla polimórfica `taggables` — un tag puede etiquetar Companies,
 * Contacts, Deals, Tickets, etc.
 *
 * Patron Spatie tags pero simplificado (sin slug por idioma — un nombre
 * por tag por workspace).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 80)->index();

            // Color hex para visualización en UI (#RRGGBB o named).
            $table->string('color', 16)->default('#888888');

            $table->string('description', 255)->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Un tag con el mismo nombre solo puede existir 1 vez por workspace.
            $table->unique(['tenant_id', 'name'], 'tags_tenant_name_unique');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
