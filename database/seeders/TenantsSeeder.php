<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TenantsSeeder — workspaces de prueba para desarrollo.
 *
 * Cada tenant es una "empresa cliente" del SaaS:
 *   - Empresa 1     (id=1) → admin: antonio@gmail.com
 *   - Empresa 2     (id=2) → admin: hector@gmail.com
 *   - Independiente (id=3) → admin: independiente@gmail.com
 *
 * Cada tenant tiene un system_user invisible asociado al final del seed
 * (creado por TenantSystemUserService). Ese usuario es el dueno de los
 * tokens API de Sanctum: sin el no hay integracion externa posible.
 *
 * Idempotente: usa updateOrInsert por id (el slug se preserva si ya existia).
 */
class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        // Cada tenant pre-configurado con su moneda default (todos peruanos
        // por ahora — Empresa 1 / Empresa 2 / Independiente usan PEN excepto
        // Independiente que opera en USD por ser proyecto freelance). Esto se
        // hereda a companies/deals/quotes/invoices que se creen bajo cada workspace.
        $tenants = [
            ['id' => 1, 'name' => 'Empresa 1',     'default_currency_code' => 'PEN'],
            ['id' => 2, 'name' => 'Empresa 2',     'default_currency_code' => 'PEN'],
            ['id' => 3, 'name' => 'Independiente', 'default_currency_code' => 'USD'],
        ];

        // Timezone explícito en cada workspace seed. Sin esto, el booted()
        // del modelo intentaria derivarlo del country del creator — pero
        // como insertamos via DB::table() (raw, sin eventos del modelo) el
        // hook no corre. Mejor ser explícitos.
        foreach ($tenants as $t) {
            $existingSlug = DB::table('tenants')->where('id', $t['id'])->value('slug');
            DB::table('tenants')->updateOrInsert(
                ['id' => $t['id']],
                [
                    'slug'                  => $existingSlug ?? Str::random(22),
                    'name'                  => $t['name'],
                    'is_active'             => true,
                    'timezone'              => 'America/Lima',
                    'default_currency_code' => $t['default_currency_code'],
                    'created_by'            => 1,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]
            );
        }

        // Reset auto-increment para que el proximo INSERT continue despues del ultimo id.
        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT setval('tenants_id_seq', COALESCE((SELECT MAX(id) FROM tenants), 0) + 1, false)");
        }

        $this->command?->info('Tenants seeded: Empresa 1 (id=1), Empresa 2 (id=2), Independiente (id=3).');

        // System users — invisibles, duenos de los tokens API. Idempotente.
        $service = app(\App\Services\SystemManagement\TenantSystemUserService::class);
        foreach (\App\Models\Tenant::all() as $tenant) {
            $service->ensureFor($tenant);
        }
        $this->command?->info('System users created/linked for all tenants.');
    }
}
