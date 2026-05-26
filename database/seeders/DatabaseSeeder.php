<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master seeder.
 *
 * Order matters because of foreign keys:
 *   1. Languages       → no FK
 *   2. Regions         → no FK to other seeded tables
 *   3. Locales         → needs language_id
 *   4. Countries       → needs region_id + default_locale_id
 *   5. SystemModules   → no FK
 *   6. RolesAndPermissions (1st pass) → needs system_modules. Creates roles
 *      (super, admin, user, api) + permissions. User assignments warn
 *      because users don't exist yet — that's expected on this pass.
 *   7. Tenants         → creates workspaces + auto-creates a "system user"
 *      per workspace (needs roles + countries + locales).
 *   8. Users           → creates the human users (Carlos, antonio, jose, etc.).
 *   9. RolesAndPermissions (2nd pass) → assigns role to each seeded user by email.
 *
 * Para data fake de prueba (benchmarking), ver el bloque comentado al final de
 * RegionsSeeder.php — 1000 regiones generadas con nombres realistas.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Master / catalog data ───────────────────────────────────
            LanguagesSeeder::class,
            RegionsSeeder::class,
            LocalesSeeder::class,
            CountriesSeeder::class,
            CurrenciesSeeder::class,    // 40 ISO 4217 — base para deals/quotes/invoices
            IndustriesSeeder::class,    // 40 industrias B2B estandar (HubSpot ref)
            SystemModulesSeeder::class,

            // ── Pricing tiers (free/basic/pro/enterprise) ───────────────
            PlansSeeder::class,

            // ── Settings globales (25 keys: app, features, downloads,
            //    notifications, security, exports, uploads, audit, bulk).
            //    Critical: muchos servicios leen Setting::get(...) y sin
            //    estos defaults el comportamiento cae a hardcode o falla.
            SettingsSeeder::class,

            // ── Roles + permissions (1st pass — definitions only) ──────
            RolesAndPermissionsSeeder::class,

            // ── Tenants + per-tenant system user ────────────────────────
            TenantsSeeder::class,

            // ── Human users ─────────────────────────────────────────────
            UsersSeeder::class,

            // ── Roles + permissions (2nd pass — assigns roles to users) ─
            RolesAndPermissionsSeeder::class,

            // ── Custom permissions: los que el super creó desde la UI
            //    (SystemModules → agregar acción). Repuebla desde snapshot
            //    JSON. Idempotente: si están todos, no hace nada.
            CustomPermissionsSeeder::class,

            // ── Roles custom de demostracion en Empresa 1 y 2 + asignacion a
            //    sus workers. Demuestra el patron real de delegacion en un
            //    workspace con team_management. Idempotente.
            ExampleTenantRolesSeeder::class,

            // ── Workspace "Estudio Perez" — admin solo, plan free (sin
            //    suscripcion). Cubre el tier free del demo base.
            ExamplePersonalWorkspaceSeeder::class,

            // ── Suscripciones del demo base: Empresa 1 → enterprise,
            //    Empresa 2 → pro, Independiente → basic. El plan se deriva
            //    de la suscripcion vigente.
            ExampleSubscriptionsSeeder::class,
        ]);
    }
}
