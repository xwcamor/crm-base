<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemModulesSeeder extends Seeder
{
    public function run(): void
    {
        // Módulos que admin_empresarial puede asignar a sus roles.
        // Cada entry produce 7 permissions canónicas (view, show, create, edit,
        // delete, export, import) vía SystemModuleObserver::CANONICAL_ACTIONS.
        //
        // Los módulos CORE (system_modules, tenants, regions, languages, countries,
        // locales, settings) NO se registran acá:
        //   1. Sus rutas están protegidas por `role:super` middleware, no por `permission:*`.
        //   2. super tiene Gate::before bypass — pasa toda check sin chequear.
        //   3. admin_empresarial nunca debe asignar esas permissions a sus roles.
        // → Crear esas permissions sería poblar rows fantasma que no gating nada.
        //
        // Importante: usamos Eloquent (`SystemModule::firstOrCreate`) en lugar de
        // `DB::table()->updateOrInsert` para que el Observer dispare y cree las
        // permissions canónicas automáticamente al inserción.
        // NOTA: `audit_logs` y `dashboards` NO se registran:
        //   - audit_logs: read-only cross-cutting, gated por `role:super|admin`
        //     en routes. No tiene CRUD → no necesita permissions.
        //   - dashboards: landing post-login, gated solo por `auth`. Cualquier user
        //     autenticado entra (incluso sin rol).
        // Si en el futuro admin necesita delegar acceso a audit a un rol custom
        // (ej: "Auditor"), agrega `audit_logs` aquí y se generan las permissions.
        $modules = [
            // Módulos accesibles por admin_empresarial (CRUD real).
            ['name' => 'Users',          'permission_key' => 'users'],
            ['name' => 'Roles',          'permission_key' => 'roles'],

            // Customers — primer módulo de negocio real. Generado con make:module
            // como patrón clonable para los siguientes (Patients, Inventory, etc.).
            ['name' => 'Customers',      'permission_key' => 'customers'],

            // Activities — modulo polimorfico de seguimiento (notes/calls/emails/
            // meetings/tasks) que se cuelga de Deal/Company/Contact.
            ['name' => 'Activities',     'permission_key' => 'activities'],

            // Discounts — vouchers / promo codes Tier 1. Per-tenant CRUD con
            // exports, imports, edit-all, soft delete, audit, favoritos.
            ['name' => 'Discounts',      'permission_key' => 'discounts'],

            // Price Lists — listas de precios per segmento Tier 1. Per-tenant
            // CRUD con exports, imports, edit-all, soft delete, audit, favoritos.
            ['name' => 'Price Lists',    'permission_key' => 'price_lists'],

            // Stock Takes — conteos fisicos / inventarios Tier 1. Per-tenant
            // CRUD con exports, imports, edit-all, soft delete, audit, favoritos.
            ['name' => 'Stock Takes',    'permission_key' => 'stock_takes'],

            // Product Categories — taxonomia jerarquica del catalogo Tier 1.
            // Migrado desde CatalogController (catalog-lite) al patron Discount.
            ['name' => 'Product Categories', 'permission_key' => 'product_categories'],

            // Product Variants — variantes (talle/color/etc.) del catalogo Tier 1.
            // Migrado desde CatalogController (catalog-lite) al patron ProductCategory.
            ['name' => 'Product Variants',   'permission_key' => 'product_variants'],

            // Lead Sources — fuentes de leads (web, referido, evento, etc.) Tier 1.
            // Migrado desde CatalogController (catalog-lite) al patron Discount.
            ['name' => 'Lead Sources',       'permission_key' => 'lead_sources'],

            // Deliveries — entregas fisicas (fulfillment) contra sales orders.
            // Tier 1: CRUD con exports, imports, edit-all, soft delete, audit, favoritos.
            ['name' => 'Deliveries',     'permission_key' => 'deliveries'],

            // Payment Methods — catalogo de formas de cobro per-tenant Tier 1.
            // Migrado desde CatalogController (catalog-lite) al patron Discount/ProductCategory.
            ['name' => 'Payment Methods', 'permission_key' => 'payment_methods'],

            // Exchange Rates — historial FX entre monedas Tier 1. Append-only
            // por uso pero CRUD completo con exports, imports, edit-all, soft
            // delete, audit, favoritos.
            ['name' => 'Exchange Rates', 'permission_key' => 'exchange_rates'],

            // Reports — agregaciones read-only sobre Deals/Invoices/Activities/Stock.
            // No tiene CRUD (no genera datos propios). Solo necesita la canonical
            // permission `reports.view`; el resto (create/edit/delete/export/import)
            // se crean igual por el Observer pero quedan sin uso — no es problema,
            // mantiene homogeneidad del esquema y admin puede asignar/sacar el view.
            ['name' => 'Reports', 'permission_key' => 'reports'],

            // ─── Modulos CRM (Tier 1) ─────────────────────────────────────────
            // El sidebar requiere can('X.view') para mostrar estas entradas. Sin
            // registro en system_modules, los permisos no se generan y admin no
            // puede ver estos modulos aunque tenga "todos los permisos existentes".
            ['name' => 'Companies',  'permission_key' => 'companies'],
            ['name' => 'Contacts',   'permission_key' => 'contacts'],
            ['name' => 'Pipelines',  'permission_key' => 'pipelines'],
            ['name' => 'Deals',      'permission_key' => 'deals'],

            // ─── Modulos de negocio (Tier 1) ──────────────────────────────────
            ['name' => 'Products',         'permission_key' => 'products'],
            ['name' => 'Quotes',           'permission_key' => 'quotes'],
            ['name' => 'Invoices',         'permission_key' => 'invoices'],
            ['name' => 'Sales Orders',     'permission_key' => 'sales_orders'],
            ['name' => 'Purchase Orders',  'permission_key' => 'purchase_orders'],
            ['name' => 'Payments',         'permission_key' => 'payments'],
            ['name' => 'Warehouses',       'permission_key' => 'warehouses'],
            ['name' => 'Stock',            'permission_key' => 'stock'],
            ['name' => 'Tax Classes',      'permission_key' => 'tax_classes'],
        ];

        foreach ($modules as $m) {
            \App\Models\SystemModule::firstOrCreate(
                ['permission_key' => $m['permission_key']],
                [
                    'slug'       => Str::random(22),
                    'name'       => $m['name'],
                    'created_by' => 1,
                ]
            );
        }
    }
}
