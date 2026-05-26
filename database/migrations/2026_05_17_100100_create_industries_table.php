<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * industries — taxonomía global de industrias para clasificar Companies.
 *
 * Patrón estandar HubSpot/Salesforce: lista cerrada de industrias comunes
 * (Software, Manufacturing, Retail, Healthcare, etc.). Se permite jerarquía
 * via parent_id para que el tenant pueda agrupar (ej: Software > SaaS > B2B).
 *
 * Global (sin tenant_id) para evitar duplicar catálogos por workspace. Si un
 * tenant quiere industrias custom, se permite via parent_id apuntando a una
 * "global" raíz, pero esa flexibilidad la define el módulo Industries.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name', 120)->index();

            // Taxonomía jerárquica: parent_id nullable apunta a otra industry.
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('industries')->nullOnDelete();
            $table->index('is_active');
            $table->index('parent_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
