<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * files — adjuntos polimórficos para Company/Contact/Deal/Ticket/Quote/etc.
 *
 * Storage por defecto: disk 'local' (per CLAUDE.md: el usuario solo guarda
 * fotos perfil, logos, imports — no usa S3). El campo `disk` permite migrar
 * a S3 en el futuro sin tocar schema.
 *
 * `checksum_sha256` opcional para deduplicación y verificación de integridad.
 * `path` apunta al storage real (formato 'tenants/{id}/files/{slug}.{ext}'
 * lo construye la app, no el schema).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->morphs('fileable');   // Company, Contact, Deal, Ticket, Quote, Note

            $table->string('disk', 30)->default('local');
            $table->string('path', 500);             // Ruta dentro del disk
            $table->string('original_name', 255);     // Nombre original del archivo
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            // Hash para dedup + integridad (opcional).
            $table->string('checksum_sha256', 64)->nullable()->index();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index(['fileable_type', 'fileable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
