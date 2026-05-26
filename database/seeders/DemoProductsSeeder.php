<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();

        foreach ($tenants as $tenant) {
            if (Product::where('tenant_id', $tenant->id)->exists()) {
                $this->command?->warn("  · {$tenant->name}: ya tiene products, salteado.");
                continue;
            }

            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', 'not like', 'api+%')
                ->orderBy('id')
                ->first();

            if (!$admin) {
                $this->command?->warn("  · {$tenant->name}: sin admin, salteado.");
                continue;
            }

            DB::transaction(fn () => $this->seedForTenant($tenant, $admin));
            $this->command?->info("  · {$tenant->name}: demo products creada.");
        }
    }

    protected function seedForTenant(Tenant $tenant, User $admin): void
    {
        $categories = [
            'Software'          => null,
            'Licencias'         => 'Software',
            'Suscripciones'     => 'Software',
            'Servicios'         => null,
            'Consultoría'       => 'Servicios',
            'Implementación'    => 'Servicios',
            'Hardware'          => null,
            'Accesorios'        => 'Hardware',
        ];

        $catModels = [];
        foreach ($categories as $name => $parentName) {
            $parentId = $parentName ? ($catModels[$parentName]?->id ?? null) : null;
            $catModels[$name] = ProductCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
                [
                    'slug'       => Str::random(22),
                    'sort_order' => 0,
                    'is_active'  => true,
                    'created_by' => $admin->id,
                ]
            );
        }

        $currency = $tenant->default_currency_code ?? 'USD';

        $products = [
            // Software / Licencias
            ['name' => 'Licencia Pro Anual — 50 usuarios', 'sku' => 'LIC-PRO-50',  'type' => 'good',         'category' => 'Licencias',     'brand' => 'AppCorp', 'cost' => 8000,  'list_price' => 18000, 'description' => 'Licencia anual, 50 usuarios concurrentes.'],
            ['name' => 'Licencia Enterprise — Unlimited', 'sku' => 'LIC-ENT-UNL', 'type' => 'good',         'category' => 'Licencias',     'brand' => 'AppCorp', 'cost' => 18000, 'list_price' => 42000, 'description' => 'Licencia anual sin límite de usuarios.'],
            ['name' => 'Plan Starter mensual',             'sku' => 'SUB-START',   'type' => 'subscription', 'category' => 'Suscripciones', 'brand' => 'AppCorp', 'cost' => 0,     'list_price' => 99,    'billing_cycle' => 'monthly',  'billing_period' => 1, 'description' => 'Plan básico mensual hasta 5 usuarios.'],
            ['name' => 'Plan Pro mensual',                 'sku' => 'SUB-PRO',     'type' => 'subscription', 'category' => 'Suscripciones', 'brand' => 'AppCorp', 'cost' => 0,     'list_price' => 299,   'billing_cycle' => 'monthly',  'billing_period' => 1, 'description' => 'Plan pro mensual hasta 25 usuarios.'],
            ['name' => 'Plan Enterprise anual',            'sku' => 'SUB-ENT-Y',   'type' => 'subscription', 'category' => 'Suscripciones', 'brand' => 'AppCorp', 'cost' => 0,     'list_price' => 24000, 'billing_cycle' => 'yearly',   'billing_period' => 1, 'description' => 'Plan enterprise anual con SLA.'],

            // Servicios / Consultoría
            ['name' => 'Hora de consultoría senior',       'sku' => 'CONS-SR',     'type' => 'service',      'category' => 'Consultoría',   'cost' => 80,    'list_price' => 180, 'description' => 'Consultoría especializada — hora.'],
            ['name' => 'Implementación llave en mano',     'sku' => 'IMPL-FULL',   'type' => 'service',      'category' => 'Implementación','cost' => 4000,  'list_price' => 9500, 'description' => 'Setup completo + migración de data + training.'],
            ['name' => 'Training corporativo (8 hrs)',     'sku' => 'TRAIN-8H',    'type' => 'service',      'category' => 'Implementación','cost' => 500,   'list_price' => 1800, 'description' => 'Capacitación presencial al equipo.'],
            ['name' => 'Soporte premium anual',            'sku' => 'SUP-PREM-Y',  'type' => 'subscription', 'category' => 'Servicios',     'cost' => 0,     'list_price' => 6000, 'billing_cycle' => 'yearly', 'billing_period' => 1, 'description' => 'SLA 4hrs + canal directo.'],

            // Hardware
            ['name' => 'Lector código de barras USB',      'sku' => 'HW-SCAN-USB', 'barcode' => '7501234567890', 'type' => 'good', 'category' => 'Accesorios', 'brand' => 'BarTek', 'cost' => 35,  'list_price' => 89,  'weight_kg' => 0.25, 'low_stock_threshold' => 5, 'description' => 'Scanner USB plug-and-play.'],
            ['name' => 'Impresora térmica de tickets',     'sku' => 'HW-PRT-80',   'barcode' => '7502345678901', 'type' => 'good', 'category' => 'Hardware',   'brand' => 'TermoTek', 'cost' => 95,  'list_price' => 235, 'weight_kg' => 1.4,  'length_cm' => 18, 'width_cm' => 13, 'height_cm' => 10, 'low_stock_threshold' => 3, 'description' => 'Impresora 80mm Ethernet/USB.'],
            ['name' => 'Tablet POS 10"',                   'sku' => 'HW-TAB-10',   'barcode' => '7503456789012', 'type' => 'good', 'category' => 'Hardware',   'brand' => 'GenericTab', 'cost' => 180, 'list_price' => 420, 'weight_kg' => 0.5, 'low_stock_threshold' => 4, 'description' => 'Tablet 10" para punto de venta.'],

            // Bundle
            ['name' => 'Bundle POS completo',              'sku' => 'BNDL-POS',    'type' => 'bundle',       'category' => 'Hardware',      'cost' => 320,   'list_price' => 750, 'description' => 'Tablet + Impresora térmica + Scanner — kit POS llave en mano.'],
        ];

        foreach ($products as $p) {
            $categoryId = isset($p['category']) ? ($catModels[$p['category']]->id ?? null) : null;
            unset($p['category']);
            Product::create(array_merge($p, [
                'slug'           => Str::random(22),
                'tenant_id'      => $tenant->id,
                'created_by'     => $admin->id,
                'category_id'    => $categoryId,
                'currency_code'  => $currency,
                'track_inventory'=> ($p['type'] ?? 'good') === 'good',
                'is_active'      => true,
            ]));
        }
    }
}
