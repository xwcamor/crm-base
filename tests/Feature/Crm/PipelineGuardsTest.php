<?php

namespace Tests\Feature\Crm;

use App\Models\Pipeline;

/**
 * Cubre las dos reglas de negocio criticas del modulo Pipelines:
 *   1) Default unico por workspace (un solo is_default=true por tenant)
 *   2) Borrado bloqueado si el pipeline tiene deals abiertos
 */
class PipelineGuardsTest extends PipelineTestCase
{
    public function test_creating_default_pipeline_unsets_previous_default(): void
    {
        $this->actingAsTenantAdmin(1);

        $old = Pipeline::factory()->create([
            'tenant_id'  => 1,
            'name'       => 'Old Default',
            'is_default' => true,
        ]);

        $this->post(route('crm.pipelines.store'), [
            'name'       => 'New Default',
            'is_default' => true,
            'is_active'  => true,
        ])->assertRedirect();

        $new = Pipeline::where('name', 'New Default')->firstOrFail();

        $this->assertTrue($new->fresh()->is_default, 'New pipeline should be default');
        $this->assertFalse($old->fresh()->is_default, 'Old default should have been unset');
    }

    public function test_updating_pipeline_to_default_unsets_previous_default(): void
    {
        $this->actingAsTenantAdmin(1);

        $first = Pipeline::factory()->create([
            'tenant_id'  => 1,
            'name'       => 'First',
            'is_default' => true,
        ]);
        $second = Pipeline::factory()->create([
            'tenant_id'  => 1,
            'name'       => 'Second',
            'is_default' => false,
        ]);

        $this->put(route('crm.pipelines.update', $second->slug), [
            'name'       => 'Second',
            'is_default' => true,
            'is_active'  => true,
        ])->assertRedirect();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_default_guard_does_not_cross_tenants(): void
    {
        $tenant1Default = Pipeline::factory()->create([
            'tenant_id'  => 1,
            'name'       => 'T1 Default',
            'is_default' => true,
        ]);

        $this->actingAsTenantAdmin(2);
        $this->post(route('crm.pipelines.store'), [
            'name'       => 'T2 Default',
            'is_default' => true,
            'is_active'  => true,
        ])->assertRedirect();

        // El default del tenant 1 NO debe haber sido afectado por el create del tenant 2.
        $this->assertTrue($tenant1Default->fresh()->is_default);
    }

    public function test_cannot_delete_pipeline_with_open_deals(): void
    {
        $this->actingAsTenantAdmin(1);

        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Has Deals']);
        $stage    = $this->makeStage($pipeline->id, 1);
        $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->delete(route('crm.pipelines.deleteSave', $pipeline->slug), [
            'deleted_description' => 'Intento borrar.',
        ]);

        $response->assertSessionHasErrors('pipeline');
        $this->assertDatabaseHas('pipelines', [
            'id'         => $pipeline->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_delete_pipeline_when_deals_are_closed(): void
    {
        $this->actingAsTenantAdmin(1);

        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Closed Deals']);
        $stage    = $this->makeStage($pipeline->id, 1);
        // Deal cerrado (status != 'open') no debe contar como bloqueante
        $this->makeOpenDeal($pipeline->id, $stage->id, 1, ['status' => 'won', 'won_at' => now()]);

        $response = $this->delete(route('crm.pipelines.deleteSave', $pipeline->slug), [
            'deleted_description' => 'Cierre del pipeline.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('pipelines', ['id' => $pipeline->id]);
    }

    public function test_can_delete_pipeline_with_no_deals(): void
    {
        $this->actingAsTenantAdmin(1);

        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Empty']);

        $response = $this->delete(route('crm.pipelines.deleteSave', $pipeline->slug), [
            'deleted_description' => 'Sin deals.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('pipelines', ['id' => $pipeline->id]);
    }
}
