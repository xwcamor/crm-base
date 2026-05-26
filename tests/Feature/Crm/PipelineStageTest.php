<?php

namespace Tests\Feature\Crm;

use App\Models\Pipeline;
use App\Models\PipelineStage;

/**
 * Cubre el sub-recurso pipeline_stages: create/update/destroy/reorder + sus
 * guards (won/lost mutuamente excluyentes, name unico por pipeline, no
 * borrar stage con deals).
 */
class PipelineStageTest extends PipelineTestCase
{
    public function test_admin_can_create_stage(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'P']);

        $response = $this->post(route('crm.pipelines.stages.store', $pipeline->slug), [
            'name'            => 'Prospección',
            'color'           => '#94a3b8',
            'probability_pct' => 10,
            'rot_days'        => 30,
            'is_active'       => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipeline_stages', [
            'pipeline_id' => $pipeline->id,
            'name'        => 'Prospección',
            'tenant_id'   => 1,
        ]);
    }

    public function test_stage_name_must_be_unique_within_pipeline(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $this->makeStage($pipeline->id, 1, ['name' => 'Calificación']);

        $response = $this->post(route('crm.pipelines.stages.store', $pipeline->slug), [
            'name' => 'Calificación',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_same_stage_name_allowed_in_different_pipelines(): void
    {
        $this->actingAsTenantAdmin(1);
        $p1 = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Sales']);
        $p2 = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'Renewals']);
        $this->makeStage($p1->id, 1, ['name' => 'Propuesta']);

        $response = $this->post(route('crm.pipelines.stages.store', $p2->slug), [
            'name' => 'Propuesta',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipeline_stages', [
            'pipeline_id' => $p2->id,
            'name'        => 'Propuesta',
        ]);
    }

    public function test_won_and_lost_are_mutually_exclusive(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);

        $response = $this->post(route('crm.pipelines.stages.store', $pipeline->slug), [
            'name'    => 'Inválida',
            'is_won'  => true,
            'is_lost' => true,
        ]);

        $response->assertSessionHasErrors('is_won');
    }

    public function test_update_stage(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1, ['name' => 'Original']);

        $response = $this->put(route('crm.pipelines.stages.update', [$pipeline->slug, $stage->slug]), [
            'name'            => 'Renamed',
            'probability_pct' => 75,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipeline_stages', [
            'id'              => $stage->id,
            'name'            => 'Renamed',
            'probability_pct' => 75,
        ]);
    }

    public function test_cannot_delete_stage_with_deals(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->delete(route('crm.pipelines.stages.destroy', [$pipeline->slug, $stage->slug]));

        $response->assertSessionHasErrors('stage');
        $this->assertDatabaseHas('pipeline_stages', [
            'id'         => $stage->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_delete_empty_stage(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);

        $response = $this->delete(route('crm.pipelines.stages.destroy', [$pipeline->slug, $stage->slug]));

        $response->assertRedirect();
        $this->assertSoftDeleted('pipeline_stages', ['id' => $stage->id]);
    }

    public function test_reorder_stages(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $s1 = $this->makeStage($pipeline->id, 1, ['name' => 'A', 'sort_order' => 1]);
        $s2 = $this->makeStage($pipeline->id, 1, ['name' => 'B', 'sort_order' => 2]);
        $s3 = $this->makeStage($pipeline->id, 1, ['name' => 'C', 'sort_order' => 3]);

        // Reverso: C, B, A
        $response = $this->post(route('crm.pipelines.stages.reorder', $pipeline->slug), [
            'order' => [$s3->id, $s2->id, $s1->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, $s3->fresh()->sort_order);
        $this->assertEquals(2, $s2->fresh()->sort_order);
        $this->assertEquals(3, $s1->fresh()->sort_order);
    }

    public function test_cannot_update_stage_from_another_pipeline(): void
    {
        $this->actingAsTenantAdmin(1);
        $p1 = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'P1']);
        $p2 = Pipeline::factory()->create(['tenant_id' => 1, 'name' => 'P2']);
        $stage = $this->makeStage($p1->id, 1, ['name' => 'X']);

        // Intentamos updatear el stage de p1 pero pasando p2 como pipeline en la URL.
        // El controller hace abort_unless($stage->pipeline_id === $pipeline->id, 404).
        $this->put(route('crm.pipelines.stages.update', [$p2->slug, $stage->slug]), [
            'name' => 'Hijack',
        ]);

        // Lo importante: el stage NO debe haber sido modificado.
        $this->assertDatabaseHas('pipeline_stages', ['id' => $stage->id, 'name' => 'X']);
        $this->assertDatabaseMissing('pipeline_stages', ['id' => $stage->id, 'name' => 'Hijack']);
    }
}
