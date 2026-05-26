<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\SalesOrder;

/**
 * Papelera + restore + force-delete de SalesOrders.
 *
 * Trash/restore/force_delete estan gateadas por `role:super` via
 * abort_unless en el controller. Admin del tenant no accede.
 *
 * Force-delete confirma con `reference_confirmation` (no `name_confirmation`
 * como Customer) — SalesOrder usa `reference` como key humana.
 */
class SalesOrderTrashTest extends SalesOrderTestCase
{
    public function test_super_can_access_trash(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('business_management.sales_orders.trash'));
        $response->assertOk();
    }

    public function test_admin_cannot_access_trash(): void
    {
        $this->actingAsTenantAdmin(1);

        // El middleware role:super dispara UnauthorizedException;
        // bootstrap/app.php lo convierte en redirect+flash error.
        $response = $this->get(route('business_management.sales_orders.trash'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_can_restore(): void
    {
        $this->actingAsSuperAdmin();
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-RES',
        ]);
        $order->delete();

        $response = $this->post(route('business_management.sales_orders.restore', $order->slug));

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'id'         => $order->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_requires_reference_match(): void
    {
        $this->actingAsSuperAdmin();
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-REAL-1',
        ]);
        $order->delete();

        $response = $this->delete(route('business_management.sales_orders.force_delete', $order->slug), [
            'reference_confirmation' => 'OV-WRONG',
            'reason'                 => 'Eliminacion definitiva por cierre.',
        ]);

        $response->assertSessionHasErrors('reference_confirmation');
        $this->assertNotNull(SalesOrder::withTrashed()->find($order->id));
    }

    public function test_force_delete_with_correct_reference_hard_deletes(): void
    {
        $this->actingAsSuperAdmin();
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-REAL-2',
        ]);
        $order->delete();

        $response = $this->delete(route('business_management.sales_orders.force_delete', $order->slug), [
            'reference_confirmation' => 'OV-REAL-2',
            'reason'                 => 'Eliminacion definitiva por cierre.',
        ]);

        $response->assertRedirect();
        $this->assertNull(SalesOrder::withTrashed()->find($order->id), 'El sales_order debe estar hard-deleted.');
    }

    public function test_bulk_delete_marks_records(): void
    {
        $this->actingAsTenantAdmin(1);

        $a = SalesOrder::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1, 'reference' => 'OV-BD-A']);
        $b = SalesOrder::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1, 'reference' => 'OV-BD-B']);
        $c = SalesOrder::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1, 'reference' => 'OV-BD-C']);

        $response = $this->post(route('business_management.sales_orders.bulk_delete'), [
            'ids'                 => [$a->id, $b->id, $c->id],
            'deleted_description' => 'Limpieza masiva.',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($a->fresh()->deleted_at);
        $this->assertNotNull($b->fresh()->deleted_at);
        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_bulk_set_status_changes_state(): void
    {
        $this->actingAsTenantAdmin(1);

        $a = SalesOrder::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1, 'reference' => 'OV-BS-A', 'status' => 'pending']);
        $b = SalesOrder::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1, 'reference' => 'OV-BS-B', 'status' => 'pending']);

        $response = $this->post(route('business_management.sales_orders.bulk_set_active'), [
            'ids'    => [$a->id, $b->id],
            'status' => 'processing',
        ]);

        $response->assertRedirect();
        $this->assertSame('processing', $a->fresh()->status);
        $this->assertSame('processing', $b->fresh()->status);
    }

    public function test_undo_last_delete_restores(): void
    {
        $this->actingAsTenantAdmin(1);
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-UNDO',
        ]);

        $this->delete(route('business_management.sales_orders.deleteSave', $order->slug), [
            'deleted_description' => 'Eliminacion temporal.',
        ]);
        $this->assertSoftDeleted('sales_orders', ['id' => $order->id]);

        $response = $this->post(route('business_management.sales_orders.undo_last_delete'));

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'id'         => $order->id,
            'deleted_at' => null,
        ]);
    }
}
