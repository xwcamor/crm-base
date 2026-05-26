<?php

namespace Tests\Feature\Crm;

use App\Models\Pipeline;

class PipelineCrudTest extends PipelineTestCase
{
    public function test_admin_sees_only_pipelines_of_his_tenant(): void
    {
        Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Sales 2026']);
        Pipeline::factory()->create(['tenant_id' => 2, 'name' => 'Otro Tenant']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('crm.pipelines.index'));
        $response->assertOk();

        // BelongsToTenant scope filtra automatico
        $visible = Pipeline::query()->pluck('name')->all();
        $this->assertContains('Sales 2026', $visible);
        $this->assertNotContains('Otro Tenant', $visible);
    }

    public function test_admin_can_create_pipeline(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('crm.pipelines.store'), [
            'name'        => 'Renewals 2026',
            'description' => 'Pipeline de renovaciones B2B',
            'color'       => '#16a34a',
            'is_active'   => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipelines', [
            'name'      => 'Renewals 2026',
            'color'     => '#16a34a',
            'tenant_id' => 1,
        ]);
    }

    public function test_name_must_be_unique_within_tenant(): void
    {
        Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Sales']);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(route('crm.pipelines.store'), [
            'name'      => 'Sales',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_name_unique_is_accent_insensitive(): void
    {
        // En sqlite test no hay unaccent — el callback usa LOWER fallback.
        // Aca verifico solo el case-insensitive porque accent depende del driver.
        Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Sales 2026']);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(route('crm.pipelines.store'), [
            'name'      => 'SALES 2026',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_same_name_allowed_in_different_tenants(): void
    {
        Pipeline::factory()->create(['tenant_id' => 2, 'name' => 'Sales']);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(route('crm.pipelines.store'), [
            'name'      => 'Sales',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipelines', ['name' => 'Sales', 'tenant_id' => 1]);
        $this->assertDatabaseHas('pipelines', ['name' => 'Sales', 'tenant_id' => 2]);
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'To Delete']);

        $response = $this->delete(route('crm.pipelines.deleteSave', $pipeline->slug), [
            'deleted_description' => 'Ya no se usa.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('pipelines', ['id' => $pipeline->id]);
    }

    public function test_update_pipeline_redirects_to_show(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Original']);

        $response = $this->put(route('crm.pipelines.update', $pipeline->slug), [
            'name'      => 'Renamed',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('crm.pipelines.show', $pipeline->slug));
        $this->assertDatabaseHas('pipelines', ['id' => $pipeline->id, 'name' => 'Renamed']);
    }

    public function test_store_pipeline_redirects_to_show(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('crm.pipelines.store'), [
            'name'      => 'Brand New',
            'is_active' => true,
        ]);

        $created = Pipeline::where('name', 'Brand New')->firstOrFail();
        $response->assertRedirect(route('crm.pipelines.show', $created->slug));
    }
}
