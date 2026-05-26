<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\StockTake;

class StockTakeCrudTest extends StockTakeTestCase
{
    public function test_admin_sees_only_stock_takes_of_his_tenant(): void
    {
        StockTake::factory()->create([
            'tenant_id' => 1, 'warehouse_id' => 1,
            'reference' => 'COUNT-T1-A',
        ]);
        StockTake::factory()->create([
            'tenant_id' => 2, 'warehouse_id' => 2,
            'reference' => 'COUNT-T2-B',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.stock_takes.index'));
        $response->assertOk();

        $visible = StockTake::query()->pluck('reference')->all();
        $this->assertContains('COUNT-T1-A', $visible);
        $this->assertNotContains('COUNT-T2-B', $visible);
    }

    public function test_admin_can_create_stock_take_for_warehouse(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.stock_takes.store'),
            $this->validStockTakePayload(['reference' => 'COUNT-NEW-001'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_takes', [
            'reference'    => 'COUNT-NEW-001',
            'tenant_id'    => 1,
            'warehouse_id' => 1,
        ]);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        StockTake::factory()->create([
            'tenant_id' => 1, 'warehouse_id' => 1,
            'reference' => 'COUNT-DUP-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(
            route('business_management.stock_takes.store'),
            // StoreStockTakeRequest aplica LOWER(reference) check → case insensitive.
            $this->validStockTakePayload(['reference' => 'count-dup-1'])
        );

        $response->assertSessionHasErrors('reference');
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $take = StockTake::factory()->create([
            'tenant_id' => 1, 'warehouse_id' => 1,
            'reference' => 'COUNT-DEL',
        ]);

        $response = $this->delete(
            route('business_management.stock_takes.deleteSave', $take->slug),
            ['deleted_description' => 'Conteo descartado.']
        );

        $response->assertRedirect();
        $this->assertSoftDeleted('stock_takes', ['id' => $take->id]);
    }

    public function test_status_workflow_draft_in_progress_completed(): void
    {
        $this->actingAsTenantAdmin(1);

        // Crear en draft.
        $response = $this->post(
            route('business_management.stock_takes.store'),
            $this->validStockTakePayload(['reference' => 'COUNT-WF-001', 'status' => 'draft'])
        );
        $response->assertRedirect();
        $take = StockTake::where('reference', 'COUNT-WF-001')->firstOrFail();
        $this->assertSame('draft', $take->status);

        // Update → in_progress.
        $response = $this->put(route('business_management.stock_takes.update', $take->slug), [
            'reference' => 'COUNT-WF-001',
            'status'    => 'in_progress',
        ]);
        $response->assertRedirect();
        $this->assertSame('in_progress', $take->fresh()->status);

        // Update → completed. El service marca completed_at + completed_by + dispara generateAdjustments.
        $response = $this->put(route('business_management.stock_takes.update', $take->slug), [
            'reference' => 'COUNT-WF-001',
            'status'    => 'completed',
        ]);
        $response->assertRedirect();
        $fresh = $take->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->completed_by);
    }

    public function test_cannot_destroy_completed_stock_take(): void
    {
        // Nota: el StockTakeService.update() no bloquea ediciones a status 'completed',
        // pero el controller.destroy() (legacy DELETE sin password) si lo bloquea con
        // un return back()->with('error', ...). Este test cubre esa guard del controller.
        $this->actingAsTenantAdmin(1);
        $take = StockTake::factory()->create([
            'tenant_id'    => 1,
            'warehouse_id' => 1,
            'reference'    => 'COUNT-COMPLETED',
            'status'       => 'completed',
        ]);

        $response = $this->delete(route('business_management.stock_takes.destroy', $take->slug));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // El registro NO se borro (sigue activo).
        $this->assertDatabaseHas('stock_takes', [
            'id'         => $take->id,
            'deleted_at' => null,
        ]);
    }
}
