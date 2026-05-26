<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\SalesOrder;

class SalesOrderCrudTest extends SalesOrderTestCase
{
    public function test_admin_sees_only_sales_orders_of_his_tenant(): void
    {
        SalesOrder::factory()->create(['tenant_id' => 1, 'reference' => 'OV-T1-A', 'company_id' => 1, 'warehouse_id' => 1]);
        SalesOrder::factory()->create(['tenant_id' => 2, 'reference' => 'OV-T2-B', 'company_id' => 2, 'warehouse_id' => 2]);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.sales_orders.index'));
        $response->assertOk();

        $visible = SalesOrder::query()->pluck('reference')->all();
        $this->assertContains('OV-T1-A', $visible);
        $this->assertNotContains('OV-T2-B', $visible);
    }

    public function test_admin_can_create_sales_order(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.sales_orders.store'),
            $this->validOrderPayload(['reference' => 'OV-NEW-001'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'reference' => 'OV-NEW-001',
            'tenant_id' => 1,
        ]);
        // El item se grabo y los totales se recomputaron.
        $order = SalesOrder::where('reference', 'OV-NEW-001')->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertEqualsWithDelta(100.00, (float) $order->grand_total, 0.01);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-DUP-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(
            route('business_management.sales_orders.store'),
            // Case+accent insensitive: 'ov-dup-1' debe colisionar con 'OV-DUP-1'.
            $this->validOrderPayload(['reference' => 'ov-dup-1'])
        );

        $response->assertSessionHasErrors('reference');
    }

    public function test_same_reference_allowed_in_different_tenants(): void
    {
        SalesOrder::factory()->create([
            'tenant_id' => 2, 'company_id' => 2, 'warehouse_id' => 2,
            'reference' => 'OV-SHARED-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $this->post(
            route('business_management.sales_orders.store'),
            $this->validOrderPayload(['reference' => 'OV-SHARED-1'])
        );

        $this->assertDatabaseHas('sales_orders', ['reference' => 'OV-SHARED-1', 'tenant_id' => 1]);
        $this->assertDatabaseHas('sales_orders', ['reference' => 'OV-SHARED-1', 'tenant_id' => 2]);
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-DEL',
        ]);

        $response = $this->delete(
            route('business_management.sales_orders.deleteSave', $order->slug),
            ['deleted_description' => 'Orden cancelada por cliente.']
        );

        $response->assertRedirect();
        $this->assertSoftDeleted('sales_orders', ['id' => $order->id]);
    }

    public function test_store_sales_order_returns_to_index(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.sales_orders.store'),
            $this->validOrderPayload(['reference' => 'OV-IDX-001'])
        );

        $response->assertRedirect(route('business_management.sales_orders.index'));
    }

    public function test_update_sales_order_redirects_to_show(): void
    {
        $this->actingAsTenantAdmin(1);
        $order = SalesOrder::factory()->create([
            'tenant_id' => 1, 'company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'OV-UPD',
        ]);

        $response = $this->put(
            route('business_management.sales_orders.update', $order->slug),
            $this->validOrderPayload(['reference' => 'OV-UPD-2'])
        );

        $response->assertRedirect(route('business_management.sales_orders.show', $order->slug));
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'reference' => 'OV-UPD-2']);
    }

    public function test_cannot_save_without_items(): void
    {
        $this->actingAsTenantAdmin(1);

        $payload = $this->validOrderPayload(['reference' => 'OV-NOITEMS']);
        unset($payload['items']);

        $response = $this->post(route('business_management.sales_orders.store'), $payload);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('sales_orders', ['reference' => 'OV-NOITEMS']);
    }
}
