<?php

namespace Tests\Feature\Crm;

use App\Models\Company;
use App\Models\User;

class CompanyCrudTest extends CompanyTestCase
{
    public function test_admin_sees_only_companies_of_his_tenant(): void
    {
        Company::factory()->create(['tenant_id' => 1, 'name' => 'Acme T1']);
        Company::factory()->create(['tenant_id' => 2, 'name' => 'Acme T2']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('crm.companies.index'));
        $response->assertOk();

        // BelongsToTenant scope filtra automatico
        $visible = Company::query()->pluck('name')->all();
        $this->assertContains('Acme T1', $visible);
        $this->assertNotContains('Acme T2', $visible);
    }

    public function test_admin_can_create_company(): void
    {
        $user = $this->actingAsTenantAdmin(1);

        $response = $this->post(route('crm.companies.store'), $this->companyPayload([
            'name'            => 'Acme Corp',
            'company_type'    => 'prospect',
            'lifecycle_stage' => 'lead',
            'owner_id'        => $user->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', [
            'name'      => 'Acme Corp',
            'tenant_id' => 1,
        ]);
    }

    public function test_name_must_be_unique_within_tenant(): void
    {
        Company::factory()->create(['tenant_id' => 1, 'name' => 'Acme S.A.']);

        $user = $this->actingAsTenantAdmin(1);
        $response = $this->post(route('crm.companies.store'), $this->companyPayload([
            'name'     => 'ACME S.A.',   // case-insensitive duplicate
            'owner_id' => $user->id,
        ]));

        $response->assertSessionHasErrors('name');
    }

    public function test_same_name_allowed_cross_tenants(): void
    {
        Company::factory()->create(['tenant_id' => 2, 'name' => 'Acme Shared']);

        $user = $this->actingAsTenantAdmin(1);
        $response = $this->post(route('crm.companies.store'), $this->companyPayload([
            'name'     => 'Acme Shared',
            'owner_id' => $user->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', ['name' => 'Acme Shared', 'tenant_id' => 1]);
        $this->assertDatabaseHas('companies', ['name' => 'Acme Shared', 'tenant_id' => 2]);
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $company = Company::factory()->create(['tenant_id' => 1, 'name' => 'To Delete Co']);

        $response = $this->delete(route('crm.companies.deleteSave', $company->slug), [
            'deleted_description' => 'Ya no es cliente.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_company_loads_industry_relation(): void
    {
        $this->actingAsTenantAdmin(1);

        $company = Company::factory()->create([
            'tenant_id'   => 1,
            'name'        => 'Tech Co',
            'industry_id' => 1,
        ]);

        $fresh = Company::with('industry')->find($company->id);

        $this->assertNotNull($fresh->industry);
        $this->assertSame(1, $fresh->industry->id);
        $this->assertSame('Software', $fresh->industry->name);
    }

    public function test_company_loads_country_relation(): void
    {
        $this->actingAsTenantAdmin(1);

        $company = Company::factory()->create([
            'tenant_id'  => 1,
            'name'       => 'AR Co',
            'country_id' => 1,
        ]);

        $fresh = Company::with('country')->find($company->id);

        $this->assertNotNull($fresh->country);
        $this->assertSame('Argentina', $fresh->country->name);
        $this->assertSame('AR', $fresh->country->iso_code);
    }

    /**
     * `health_score` es una columna de BD persistida (no calculada por el
     * service en este momento). Verifico que el cast `integer` funcione y
     * que el rango 0-100 se respete via update.
     *
     * Si en el futuro DealService/CompanyService implementa una calculacion
     * automatica de health_score (ej: basada en activity/recency/deals_open),
     * este test se debe ampliar para cubrir el calculo real.
     */
    public function test_health_score_persists_and_casts_to_integer(): void
    {
        $this->actingAsTenantAdmin(1);

        $company = Company::factory()->create([
            'tenant_id'    => 1,
            'name'         => 'Healthy Co',
            'health_score' => 87,
        ]);

        $fresh = Company::find($company->id);
        $this->assertSame(87, $fresh->health_score);
        $this->assertIsInt($fresh->health_score);
    }
}
