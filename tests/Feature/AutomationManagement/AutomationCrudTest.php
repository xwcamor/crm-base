<?php

namespace Tests\Feature\AutomationManagement;

use App\Models\Automation;

class AutomationCrudTest extends AutomationTestCase
{
    public function test_index_renders_for_enterprise_tenant(): void
    {
        $this->actingAsTenantAdmin();
        Automation::factory()->count(2)->create(['tenant_id' => 1]);

        $this->get(route('automation_management.automations.index'))->assertOk();
    }

    public function test_store_persists_a_schedule_daily_automation(): void
    {
        $this->actingAsTenantAdmin();

        $response = $this->post(route('automation_management.automations.store'), [
            'name'           => 'Customers inactivos diario',
            'description'    => 'Recordatorio diario por email',
            'is_active'      => true,
            'trigger_type'   => 'schedule',
            'trigger_config' => ['kind' => 'daily', 'time' => '09:00'],
            'data_source'    => 'customers',
            'data_filter'    => ['where' => [], 'limit' => 100],
            'action_type'    => 'email',
            'action_config'  => [
                'to'      => ['admin@example.com'],
                'subject' => 'Productos',
                'body'    => 'Hay {count} pendientes',
            ],
        ]);

        $response->assertRedirect();
        $automation = Automation::where('name', 'Customers inactivos diario')->first();
        $this->assertNotNull($automation);
        $this->assertSame(1, $automation->tenant_id);
        $this->assertNotNull($automation->next_run_at, 'next_run_at debe calcularse al crear');
    }

    public function test_compute_next_run_for_daily_trigger(): void
    {
        $a = Automation::factory()->create([
            'trigger_config' => ['kind' => 'daily', 'time' => '09:00'],
        ]);

        $next = $a->computeNextRunAt(now()->setTime(8, 0));

        $this->assertNotNull($next);
        $this->assertSame(9, $next->hour);
        $this->assertSame(0, $next->minute);
    }

    public function test_compute_next_run_returns_null_for_invalid_config(): void
    {
        $a = Automation::factory()->create([
            'trigger_config' => ['kind' => 'cron', 'expression' => 'invalid expression here'],
        ]);

        $this->assertNull($a->computeNextRunAt());
    }

    public function test_toggle_active_flips_is_active(): void
    {
        $user = $this->actingAsTenantAdmin();
        $a = Automation::factory()->create([
            'tenant_id'      => 1,
            'is_active'      => true,
            'trigger_config' => ['kind' => 'daily', 'time' => '09:00'],
        ]);

        $this->post(route('automation_management.automations.toggle', $a->id));

        $this->assertFalse($a->fresh()->is_active);
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin();
        $a = Automation::factory()->create(['tenant_id' => 1]);

        $response = $this->delete(route('automation_management.automations.deleteSave', $a->id), [
            'deleted_description' => 'No la uso más.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('automations', ['id' => $a->id]);
    }
}
