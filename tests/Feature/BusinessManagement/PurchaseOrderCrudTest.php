<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\PurchaseOrder;
use Illuminate\Support\Str;

class PurchaseOrderCrudTest extends PurchaseOrderTestCase
{
    public function test_admin_sees_only_purchase_orders_of_his_tenant(): void
    {
        PurchaseOrder::factory()->create([
            'tenant_id' => 1, 'supplier_company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'PO-T1-A',
        ]);
        PurchaseOrder::factory()->create([
            'tenant_id' => 2, 'supplier_company_id' => 2, 'warehouse_id' => 2,
            'reference' => 'PO-T2-B',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.purchase_orders.index'));
        $response->assertOk();

        $visible = PurchaseOrder::query()->pluck('reference')->all();
        $this->assertContains('PO-T1-A', $visible);
        $this->assertNotContains('PO-T2-B', $visible);
    }

    public function test_admin_can_create_purchase_order_with_items(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.purchase_orders.store'),
            $this->validOrderPayload(['reference' => 'PO-NEW-001'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', [
            'reference' => 'PO-NEW-001',
            'tenant_id' => 1,
        ]);
        // El item se grabo y los totales se recomputaron.
        $order = PurchaseOrder::where('reference', 'PO-NEW-001')->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertEqualsWithDelta(100.00, (float) $order->grand_total, 0.01);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        PurchaseOrder::factory()->create([
            'tenant_id' => 1, 'supplier_company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'PO-DUP-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(
            route('business_management.purchase_orders.store'),
            // StorePurchaseOrderRequest aplica LOWER(reference) check → case insensitive.
            $this->validOrderPayload(['reference' => 'po-dup-1'])
        );

        $response->assertSessionHasErrors('reference');
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $order = PurchaseOrder::factory()->create([
            'tenant_id' => 1, 'supplier_company_id' => 1, 'warehouse_id' => 1,
            'reference' => 'PO-DEL',
        ]);

        $response = $this->delete(
            route('business_management.purchase_orders.deleteSave', $order->slug),
            ['deleted_description' => 'Orden cancelada por proveedor.']
        );

        $response->assertRedirect();
        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_workflow_status_transitions(): void
    {
        $this->actingAsTenantAdmin(1);

        // Crea en draft (default del validOrderPayload).
        $response = $this->post(
            route('business_management.purchase_orders.store'),
            $this->validOrderPayload(['reference' => 'PO-WF-001', 'status' => 'draft'])
        );
        $response->assertRedirect();
        $order = PurchaseOrder::where('reference', 'PO-WF-001')->firstOrFail();
        $this->assertSame('draft', $order->status);

        // Update → submitted.
        $payload = $this->validOrderPayload(['reference' => 'PO-WF-001', 'status' => 'submitted']);
        $response = $this->put(route('business_management.purchase_orders.update', $order->slug), $payload);
        $response->assertRedirect();
        $this->assertSame('submitted', $order->fresh()->status);

        // Update → confirmed.
        $payload = $this->validOrderPayload(['reference' => 'PO-WF-001', 'status' => 'confirmed']);
        $response = $this->put(route('business_management.purchase_orders.update', $order->slug), $payload);
        $response->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);

        // Update → received.
        $payload = $this->validOrderPayload(['reference' => 'PO-WF-001', 'status' => 'received']);
        $response = $this->put(route('business_management.purchase_orders.update', $order->slug), $payload);
        $response->assertRedirect();
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_items_required_to_save(): void
    {
        $this->actingAsTenantAdmin(1);

        $payload = $this->validOrderPayload(['reference' => 'PO-NOITEMS']);
        unset($payload['items']);

        $response = $this->post(route('business_management.purchase_orders.store'), $payload);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('purchase_orders', ['reference' => 'PO-NOITEMS']);
    }
}
