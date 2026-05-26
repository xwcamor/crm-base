<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Delivery;
use App\Models\SalesOrder;

class DeliveryCrudTest extends DeliveryTestCase
{
    public function test_admin_sees_only_deliveries_of_his_tenant(): void
    {
        Delivery::factory()->create([
            'tenant_id' => 1, 'sales_order_id' => 1, 'warehouse_id' => 1,
            'reference' => 'DEL-T1-A',
        ]);
        Delivery::factory()->create([
            'tenant_id' => 2, 'sales_order_id' => 2, 'warehouse_id' => 2,
            'reference' => 'DEL-T2-B',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.deliveries.index'));
        $response->assertOk();

        $visible = Delivery::query()->pluck('reference')->all();
        $this->assertContains('DEL-T1-A', $visible);
        $this->assertNotContains('DEL-T2-B', $visible);
    }

    public function test_admin_can_create_delivery_from_sales_order(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.deliveries.store'),
            $this->validDeliveryPayload(['reference' => 'DEL-NEW-001'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('deliveries', [
            'reference'      => 'DEL-NEW-001',
            'tenant_id'      => 1,
            'sales_order_id' => 1,
        ]);
        $delivery = Delivery::where('reference', 'DEL-NEW-001')->firstOrFail();
        $this->assertCount(1, $delivery->items);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        Delivery::factory()->create([
            'tenant_id' => 1, 'sales_order_id' => 1, 'warehouse_id' => 1,
            'reference' => 'DEL-DUP-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(
            route('business_management.deliveries.store'),
            // StoreDeliveryRequest aplica LOWER(reference) check → case insensitive.
            $this->validDeliveryPayload(['reference' => 'del-dup-1'])
        );

        $response->assertSessionHasErrors('reference');
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $delivery = Delivery::factory()->create([
            'tenant_id' => 1, 'sales_order_id' => 1, 'warehouse_id' => 1,
            'reference' => 'DEL-DEL',
        ]);

        $response = $this->delete(
            route('business_management.deliveries.deleteSave', $delivery->slug),
            ['deleted_description' => 'Entrega cancelada por cliente.']
        );

        $response->assertRedirect();
        $this->assertSoftDeleted('deliveries', ['id' => $delivery->id]);
    }

    public function test_shipped_auto_fills_shipped_at_timestamp(): void
    {
        $this->actingAsTenantAdmin(1);

        // Creamos en pending → update a shipped via bulkSetActive (Service auto-fill shipped_at).
        $delivery = Delivery::factory()->create([
            'tenant_id' => 1, 'sales_order_id' => 1, 'warehouse_id' => 1,
            'reference' => 'DEL-SHIP-AUTO',
            'status'    => 'pending',
            'shipped_at' => null,
        ]);
        $this->assertNull($delivery->shipped_at);

        $response = $this->post(route('business_management.deliveries.bulk_set_active'), [
            'ids'    => [$delivery->id],
            'status' => 'shipped',
        ]);

        $response->assertRedirect();
        $fresh = $delivery->fresh();
        $this->assertSame('shipped', $fresh->status);
        $this->assertNotNull($fresh->shipped_at, 'shipped_at debe haber sido auto-completado al pasar a shipped.');
    }

    public function test_propagates_fulfillment_to_sales_order_when_shipped(): void
    {
        $this->actingAsTenantAdmin(1);

        // Pre-condition: SO-T1-DEFAULT esta en 'processing' con 1 item de qty=5, fulfilled=0.
        // Creamos una entrega con qty=5 del unico item de la SO → al pasar a 'shipped', el
        // recomputeOrderFulfillment del service marca quantity_fulfilled=5 en el item y
        // 'delivered' (todo entregado) en la SO header.
        $response = $this->post(
            route('business_management.deliveries.store'),
            $this->validDeliveryPayload([
                'reference' => 'DEL-FF-001',
                'status'    => 'shipped',
                // El validDeliveryPayload default trae quantity=1; sobreescribimos a 5.
                'items'     => [[
                    'sales_order_item_id' => 1,
                    'product_id'          => 1,
                    'quantity'            => 5,
                ]],
            ])
        );

        $response->assertRedirect();

        // El item de la SO ahora tiene quantity_fulfilled = 5.
        $this->assertDatabaseHas('sales_order_items', [
            'id'                 => 1,
            'quantity_fulfilled' => 5,
        ]);

        // La SO se promociono a 'delivered' (todo fulfillment cubierto).
        $so = SalesOrder::find(1);
        $this->assertSame('delivered', $so->status);
    }
}
