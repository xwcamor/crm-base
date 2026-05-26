<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Customer;

class CustomerCrudTest extends CustomerTestCase
{
    public function test_admin_sees_only_customers_of_his_tenant(): void
    {
        Customer::factory()->create(['tenant_id' => 1, 'name' => 'Cliente A']);
        Customer::factory()->create(['tenant_id' => 2, 'name' => 'Cliente B']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.customers.index'));
        $response->assertOk();

        // BelongsToTenant trait filtra automatico
        $visible = Customer::query()->pluck('name')->all();
        $this->assertContains('Cliente A', $visible);
        $this->assertNotContains('Cliente B', $visible);
    }

    public function test_admin_can_create_customer(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('business_management.customers.store'), [
            'name'       => 'Acme Corp',
            'cod'        => '20123456789',  // @scaffold:remove-line
            'country_id' => 1,              // @scaffold:remove-line
            'is_active'  => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'name'       => 'Acme Corp',
            'cod'        => '20123456789',  // @scaffold:remove-line
            'tenant_id'  => 1,
        ]);
    }

    // @scaffold:remove-begin commercial-uniqueness-tests
    public function test_cod_must_be_unique_within_tenant(): void
    {
        Customer::factory()->create(['tenant_id' => 1, 'cod' => 'RUC123', 'name' => 'Existing']);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(route('business_management.customers.store'), [
            'name'       => 'Duplicate',
            'cod'        => 'RUC123',
            'country_id' => 1,
        ]);

        $response->assertSessionHasErrors('cod');
    }

    public function test_same_cod_allowed_in_different_tenants(): void
    {
        Customer::factory()->create(['tenant_id' => 2, 'cod' => 'RUC123', 'name' => 'Tenant 2 Cliente']);

        $this->actingAsTenantAdmin(1);
        $response = $this->post(route('business_management.customers.store'), [
            'name'       => 'Tenant 1 Cliente',
            'cod'        => 'RUC123',
            'country_id' => 1,
        ]);

        $this->assertDatabaseHas('customers', ['name' => 'Tenant 1 Cliente', 'tenant_id' => 1]);
        $this->assertDatabaseHas('customers', ['name' => 'Tenant 2 Cliente', 'tenant_id' => 2]);
    }
    // @scaffold:remove-end

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $customer = Customer::factory()->create(['tenant_id' => 1, 'name' => 'To Delete']);

        $response = $this->delete(route('business_management.customers.deleteSave', $customer->slug), [
            'deleted_description' => 'Cliente cerrado.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
