<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * phones — números de teléfono polimórficos para Contact/Company/Lead.
 *
 * Patrón Laravel CRM: polimórfica + subscribed boolean (para SMS marketing
 * compliance) + verified_at.
 *
 * El número se guarda crudo + country_code separado para soportar formato
 * E.164 (+54 911 ...) o local sin perder origen. Validación E.164 en
 * application layer (libphonenumber).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('phones', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->morphs('phoneable');   // Contact, Company, Lead, User

            $table->string('phone', 30);                            // 911 1234 5678
            $table->string('phone_country_code', 5)->nullable();     // +54, +1, +44

            // Tipo: mobile, work, home, fax, whatsapp
            $table->string('type', 30)->default('mobile')->index();
            $table->string('label', 80)->nullable();

            $table->boolean('is_primary')->default(false);

            // Opt-in para SMS/WhatsApp marketing.
            $table->boolean('subscribed')->default(true);

            $table->timestamp('verified_at')->nullable();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('subscribed');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phones');
    }
};
