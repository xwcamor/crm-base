<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deal_contacts — m:m entre deals y contacts, con `role` por contacto.
 *
 * Ej: un deal de Acme tiene 3 contactos:
 *   - Juan (decision_maker)
 *   - María (influencer)
 *   - Pedro (technical)
 *
 * Roles tipicos: decision_maker, influencer, technical, blocker, champion, end_user.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('role', 30)->default('decision_maker');
            $table->boolean('is_primary')->default(false);
            $table->string('notes', 500)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();

            $table->unique(['deal_id', 'contact_id'], 'deal_contacts_unique');
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_contacts');
    }
};
