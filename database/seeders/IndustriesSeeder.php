<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IndustriesSeeder — 40 industrias estandar B2B (HubSpot/Salesforce ref).
 *
 * Nombres en español (locale default del proyecto). Multi-language proper
 * via `industries.{key}` namespace en lang files es un refactor futuro
 * cuando se necesite mostrar industries en otros idiomas en runtime.
 *
 * Sin jerarquía inicial (parent_id = null). Si querés sub-industrias
 * (ej: "Software > SaaS"), agregalas vía UI o ampliando este seeder.
 *
 * Idempotente vía updateOrInsert por name. Re-running solo touches updated_at.
 */
class IndustriesSeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Software',
            'Tecnologías de la información',
            'Telecomunicaciones',
            'Hardware y electrónica',
            'Manufactura',
            'Comercio minorista (Retail)',
            'E-commerce',
            'Distribución mayorista',
            'Salud',
            'Farmacéutica',
            'Biotecnología',
            'Educación',
            'Educación superior',
            'Servicios financieros',
            'Banca',
            'Seguros',
            'Inmobiliaria',
            'Construcción',
            'Arquitectura e ingeniería',
            'Automotriz',
            'Transporte y logística',
            'Energía y servicios públicos',
            'Petróleo y gas',
            'Minería',
            'Agricultura',
            'Alimentos y bebidas',
            'Hotelería',
            'Turismo y viajes',
            'Medios y entretenimiento',
            'Marketing y publicidad',
            'Servicios profesionales',
            'Servicios legales',
            'Consultoría',
            'Contabilidad',
            'Gobierno',
            'Sin fines de lucro',
            'Deportes y recreación',
            'Moda e indumentaria',
            'Belleza y cuidado personal',
            'Otro',
        ];

        foreach ($industries as $name) {
            DB::table('industries')->updateOrInsert(
                ['name' => $name],
                [
                    'slug'       => Str::random(22),
                    'parent_id'  => null,
                    'is_active'  => true,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
