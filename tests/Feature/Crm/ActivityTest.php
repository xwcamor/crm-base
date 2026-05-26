<?php

namespace Tests\Feature\Crm;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\Pipeline;

class ActivityTest extends PipelineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Necesitamos permisos de activities tambien
        foreach (['activities.view', 'activities.create', 'activities.edit', 'activities.delete'] as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $admin = \Spatie\Permission\Models\Role::findByName('admin');
        $admin->syncPermissions(\Spatie\Permission\Models\Permission::all());
    }

    public function test_admin_can_create_note_activity_on_deal(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->post(route('crm.activities.store'), [
            'type'             => 'note',
            'body'             => 'Llamé al CFO, vamos a agendar demo.',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('activities', [
            'type'             => 'note',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
            'tenant_id'        => 1,
        ]);
    }

    public function test_note_requires_body(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->post(route('crm.activities.store'), [
            'type'             => 'note',
            'body'             => '',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
        ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_call_requires_body_and_outcome(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->post(route('crm.activities.store'), [
            'type'             => 'call',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
        ]);

        $response->assertSessionHasErrors(['body', 'outcome']);
    }

    public function test_meeting_requires_subject_and_due_at(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->post(route('crm.activities.store'), [
            'type'             => 'meeting',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
        ]);

        $response->assertSessionHasErrors(['subject', 'due_at']);
    }

    public function test_task_requires_subject_and_due_at(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $response = $this->post(route('crm.activities.store'), [
            'type'             => 'task',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
        ]);

        $response->assertSessionHasErrors(['subject', 'due_at']);
    }

    public function test_mark_complete(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $activity = Activity::create([
            'type'             => 'task',
            'subject'          => 'Test task',
            'due_at'           => now()->addDay(),
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
            'tenant_id'        => 1,
            'actor_user_id'    => auth()->id(),
            'created_by'       => auth()->id(),
        ]);

        $this->assertNull($activity->completed_at);

        $response = $this->post(route('crm.activities.complete', $activity->slug));

        $response->assertRedirect();
        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_reopen(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $activity = Activity::create([
            'type'             => 'task',
            'subject'          => 'Test task',
            'due_at'           => now()->addDay(),
            'completed_at'     => now(),
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
            'tenant_id'        => 1,
            'actor_user_id'    => auth()->id(),
            'created_by'       => auth()->id(),
        ]);

        $response = $this->post(route('crm.activities.reopen', $activity->slug));

        $response->assertRedirect();
        $this->assertNull($activity->fresh()->completed_at);
    }

    public function test_tenant_isolation(): void
    {
        $pipeline1 = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage1    = $this->makeStage($pipeline1->id, 1);
        $deal1     = $this->makeOpenDeal($pipeline1->id, $stage1->id, 1);
        $admin1    = \App\Models\User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $admin1->assignRole('admin');

        Activity::create([
            'type'             => 'note',
            'body'             => 'Activity de tenant 1',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal1->id,
            'tenant_id'        => 1,
            'actor_user_id'    => $admin1->id,
            'created_by'       => $admin1->id,
        ]);

        // Tenant 2 admin no debe ver activities del tenant 1
        $this->actingAsTenantAdmin(2);
        $response = $this->get(route('crm.activities.index'));

        $response->assertOk();
        $visible = Activity::pluck('body')->all();
        $this->assertNotContains('Activity de tenant 1', $visible);
    }

    public function test_delete_activity(): void
    {
        $this->actingAsTenantAdmin(1);
        $pipeline = Pipeline::factory()->create(['tenant_id' => 1]);
        $stage    = $this->makeStage($pipeline->id, 1);
        $deal     = $this->makeOpenDeal($pipeline->id, $stage->id, 1);

        $activity = Activity::create([
            'type'             => 'note',
            'body'             => 'To delete',
            'activitable_type' => Deal::class,
            'activitable_id'   => $deal->id,
            'tenant_id'        => 1,
            'actor_user_id'    => auth()->id(),
            'created_by'       => auth()->id(),
        ]);

        $response = $this->delete(route('crm.activities.destroy', $activity->slug));

        $response->assertRedirect();
        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }
}
