<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Base TestCase para Delivery. Mismo patron que SalesOrderTestCase:
 * seed parent rows + companies + warehouses + products + sales_orders (+
 * sales_order_items) por cada tenant + roles/permissions con namespace
 * deliveries.*
 *
 * Plan enterprise activo en ambos tenants para que tests de bulk/export
 * no choquen contra plan_feature middlewares.
 */
abstract class DeliveryTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LaravelLocalizationRedirectFilter::class,
            LocaleSessionRedirect::class,
        ]);

        $this->seedParentRows();
        $this->seedRolesAndPermissions();
    }

    protected function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish',
            'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('locales')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'code' => 'es_AR',
            'name' => 'Español (AR)', 'language_id' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('regions')->insertOrIgnore([[
            'id' => 999, 'slug' => Str::random(22), 'name' => '__bootstrap__',
            'is_active' => false, 'deleted_at' => now(),
            'deleted_description' => 'Bootstrap',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('countries')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Argentina', 'iso_code' => 'AR', 'currency' => 'ARS', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru',     'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('plans')->insertOrIgnore([[
            'id' => 1, 'slug' => 'enterprise', 'name' => 'Enterprise',
            'sort_order' => 1, 'max_users' => -1, 'max_records_per_module' => -1,
            'export_rate_limit' => 50, 'support_level' => 'priority',
            'features' => json_encode([
                'export_csv' => true, 'export_excel' => true, 'export_pdf' => true,
                'export_word' => true, 'branded_exports' => true,
                'audit_log_view' => true, 'saved_views' => true,
                'bulk_operations' => true, 'imports' => true, 'edit_all' => true,
                'team_management' => true, 'api_access' => true, 'automations' => true,
                'scheduled_exports' => true, 'export_webhook_delivery' => true,
                'export_email_delivery' => true, 'extended_retention' => true,
                'higher_export_rate_limit' => true,
            ]),
            'price_monthly' => 0, 'price_yearly' => 0, 'currency' => 'USD',
            'is_active' => true, 'is_public' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'name' => 'Empresa 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'name' => 'Empresa 2', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('subscriptions')->insertOrIgnore([
            ['id' => 1, 'tenant_id' => 1, 'plan' => 'enterprise', 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'currency' => 'USD', 'payment_method' => 'manual', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tenant_id' => 2, 'plan' => 'enterprise', 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'currency' => 'USD', 'payment_method' => 'manual', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Companies (clientes) — uno por tenant. SalesOrder.company_id es required.
        DB::table('companies')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'name' => 'Cliente T1', 'is_active' => true, 'tenant_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'name' => 'Cliente T2', 'is_active' => true, 'tenant_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Warehouses — uno por tenant. Delivery.warehouse_id es required.
        DB::table('warehouses')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'code' => 'WH-T1', 'name' => 'Depósito T1', 'type' => 'main', 'is_default' => true, 'is_active' => true, 'tenant_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'code' => 'WH-T2', 'name' => 'Depósito T2', 'type' => 'main', 'is_default' => true, 'is_active' => true, 'tenant_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Products — uno por tenant. Para los items de la SO y la entrega.
        DB::table('products')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'name' => 'Producto T1', 'type' => 'good', 'list_price' => 100, 'is_active' => true, 'tenant_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'name' => 'Producto T2', 'type' => 'good', 'list_price' => 100, 'is_active' => true, 'tenant_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // SalesOrder — una por tenant. Delivery.sales_order_id es required.
        DB::table('sales_orders')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'reference' => 'SO-T1-DEFAULT', 'company_id' => 1, 'warehouse_id' => 1, 'status' => 'processing', 'payment_status' => 'unpaid', 'order_date' => now()->toDateString(), 'subtotal' => 0, 'discount_total' => 0, 'tax_total' => 0, 'shipping_cost' => 0, 'grand_total' => 0, 'tenant_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'reference' => 'SO-T2-DEFAULT', 'company_id' => 2, 'warehouse_id' => 2, 'status' => 'processing', 'payment_status' => 'unpaid', 'order_date' => now()->toDateString(), 'subtotal' => 0, 'discount_total' => 0, 'tax_total' => 0, 'shipping_cost' => 0, 'grand_total' => 0, 'tenant_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // SalesOrderItem — uno por tenant. StoreDeliveryRequest exige sales_order_item_id.
        DB::table('sales_order_items')->insertOrIgnore([
            ['id' => 1, 'sales_order_id' => 1, 'product_id' => 1, 'name' => 'Item T1', 'quantity_ordered' => 5, 'quantity_fulfilled' => 0, 'quantity_cancelled' => 0, 'unit_price' => 100, 'discount_pct' => 0, 'tax_pct' => 0, 'line_subtotal' => 500, 'line_tax' => 0, 'line_total' => 500, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'sales_order_id' => 2, 'product_id' => 2, 'name' => 'Item T2', 'quantity_ordered' => 5, 'quantity_fulfilled' => 0, 'quantity_cancelled' => 0, 'unit_price' => 100, 'discount_pct' => 0, 'tax_pct' => 0, 'line_subtotal' => 500, 'line_tax' => 0, 'line_total' => 500, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function seedRolesAndPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.delete', 'deliveries.show'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super', 'guard_name' => 'web'], ['description' => 'Test super']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['description' => 'Test admin']);
        $admin->syncPermissions(Permission::all());
    }

    protected function actingAsTenantAdmin(int $tenantId = 1): User
    {
        $u = User::factory()->create(['tenant_id' => $tenantId, 'country_id' => 1, 'locale_id' => 1]);
        $u->assignRole('admin');
        $this->actingAs($u);
        return $u;
    }

    protected function actingAsSuperAdmin(): User
    {
        $u = User::factory()->create(['tenant_id' => null, 'country_id' => 1, 'locale_id' => 1]);
        $u->assignRole('super');
        $this->actingAs($u);
        return $u;
    }

    /**
     * Payload minimal de Delivery + 1 item para POST store.
     * El test override cualquier campo via $overrides.
     */
    protected function validDeliveryPayload(array $overrides = []): array
    {
        $salesOrderId     = $overrides['sales_order_id'] ?? 1;
        $salesOrderItemId = $overrides['sales_order_item_id'] ?? 1;
        $warehouseId      = $overrides['warehouse_id'] ?? 1;
        $productId        = $overrides['product_id'] ?? 1;

        return array_merge([
            'reference'      => 'DEL-TEST-' . Str::random(6),
            'sales_order_id' => $salesOrderId,
            'warehouse_id'   => $warehouseId,
            'status'         => 'pending',
            'items' => [[
                'sales_order_item_id' => $salesOrderItemId,
                'product_id'          => $productId,
                'quantity'            => 1,
            ]],
        ], array_diff_key($overrides, ['sales_order_item_id' => true, 'product_id' => true]));
    }
}
