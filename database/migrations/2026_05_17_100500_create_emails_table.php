<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emails — direcciones de email polimórficas para Contact/Company/Lead.
 *
 * NOTA: esta tabla guarda emails ASOCIADOS A REGISTROS (work email del
 * contacto). NO es el log de emails enviados/recibidos — ese va en una
 * tabla aparte `email_messages` (futura, Fase 5: comms).
 *
 * Patrón Laravel CRM:
 *   - Polimórfica (morphTo emailable) — un contacto tiene N emails.
 *   - `subscribed` boolean para GDPR/CAN-SPAM compliance (opt-in/out).
 *   - `verified_at` y `bounced_at` para tracking de deliverability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->morphs('emailable');   // Contact, Company, Lead, User

            $table->string('email', 254);   // RFC 5321 max length

            // Tipo de email: work, personal, billing, notification, support
            $table->string('type', 30)->default('work')->index();
            $table->string('label', 80)->nullable();   // "Comercial", "Soporte", etc.

            $table->boolean('is_primary')->default(false);

            // GDPR / CAN-SPAM: si el contacto se desuscribió, no enviarle.
            $table->boolean('subscribed')->default(true);

            $table->timestamp('verified_at')->nullable();    // double opt-in
            $table->timestamp('bounced_at')->nullable();     // hard bounce

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('subscribed');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
