<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * taggables — relación polimórfica m:m entre tags y cualquier entidad.
 *
 * Permite que un tag etiquete Companies, Contacts, Deals, Tickets, etc.
 * sin necesitar tablas pivot por cada entidad.
 *
 * Patrón Laravel `morphs('taggable')` → genera taggable_type + taggable_id.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->morphs('taggable');   // taggable_type (string) + taggable_id (bigint) + index automatico

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();

            // Un mismo tag no puede aplicar 2 veces al mismo registro.
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_tag_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
