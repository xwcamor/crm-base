<?php

namespace Tests\Feature\Crm;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Company;
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
 * Base TestCase para Deals. Mismo patron + helpers para crear pipeline/
 * stage/company del workspace (todo Deal necesita estos parents).
 *
 * Currencies seedeados — currency_code es required para crear un deal.
 */
abstract class DealTestCase extends TestCase
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

        // Currencies — required para crear deals.
        DB::table('currencies')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function seedRolesAndPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ([
            'deals.view', 'deals.create', 'deals.edit', 'deals.delete', 'deals.show',
            'pipelines.view', 'pipelines.create', 'pipelines.edit', 'pipelines.delete',
            'companies.view', 'companies.create',
        ] as $perm) {
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
     * Helper: crea un Pipeline para el tenant indicado. El usuario actual NO
     * importa — el pipeline se crea via factory con tenant_id directo.
     */
    protected function makePipeline(int $tenantId, array $overrides = []): Pipeline
    {
        return Pipeline::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'name'      => 'Sales ' . Str::random(4),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Helper: PipelineStage default (no won, no lost, probability=50%).
     */
    protected function makeStage(int $pipelineId, int $tenantId, array $overrides = []): PipelineStage
    {
        return PipelineStage::create(array_merge([
            'pipeline_id'     => $pipelineId,
            'tenant_id'       => $tenantId,
            'name'            => 'Stage ' . Str::random(4),
            'color'           => '#888888',
            'sort_order'      => 1,
            'probability_pct' => 50,
            'is_won'          => false,
            'is_lost'         => false,
            'rot_days'        => 0,
            'is_active'       => true,
        ], $overrides));
    }

    /**
     * Helper: crea un Company minimal en el tenant (para FK company_id del Deal).
     */
    protected function makeCompany(int $tenantId, array $overrides = []): Company
    {
        return Company::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'name'      => 'Acme ' . Str::random(4),
        ], $overrides));
    }

    /**
     * Helper: payload base para store-deal con defaults coherentes
     * (pipeline + stage + company recien creados + currency=USD).
     */
    protected function dealPayload(array $overrides = []): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 1;
        $pipeline = $this->makePipeline($tenantId);
        $stage    = $this->makeStage($pipeline->id, $tenantId);
        $company  = $this->makeCompany($tenantId);

        return array_merge([
            'name'          => 'Deal ' . Str::random(6),
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stage->id,
            'status'        => 'open',
            'value'         => 1000,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => auth()->id(),
            'is_active'     => true,
        ], $overrides);
    }
}
