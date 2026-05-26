<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Invoice;

/**
 * Papelera + restore + force-delete de Invoices.
 *
 * Bloque trash/restore/force_delete gateado por role:super.
 * Admin tenant NO ve papelera ni puede restaurar.
 */
class InvoiceTrashTest extends InvoiceTestCase
{
    public function test_super_can_access_trash(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('business_management.invoices.trash'));
        $response->assertOk();
    }

    public function test_admin_cannot_access_trash(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->get(route('business_management.invoices.trash'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_can_restore(): void
    {
        $company = $this->makeCompany(1);
        $this->actingAsSuperAdmin();
        $invoice = Invoice::factory()->create([
            'tenant_id'  => 1,
            'company_id' => $company->id,
            'number'     => 'INV-RESTORE-001',
        ]);
        $invoice->delete();

        $response = $this->post(route('business_management.invoices.restore', $invoice->slug));

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'id'         => $invoice->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_requires_number_match(): void
    {
        $company = $this->makeCompany(1);
        $this->actingAsSuperAdmin();
        $invoice = Invoice::factory()->create([
            'tenant_id'  => 1,
            'company_id' => $company->id,
            'number'     => 'INV-FORCE-001',
        ]);
        $invoice->delete();

        $response = $this->delete(route('business_management.invoices.force_delete', $invoice->slug), [
            'name_confirmation' => 'INV-WRONG-999',
            'reason'            => 'Eliminacion definitiva por cierre fiscal.',
        ]);

        $response->assertSessionHasErrors('name_confirmation');
        $this->assertNotNull(Invoice::withTrashed()->find($invoice->id));
    }

    public function test_force_delete_with_correct_number_hard_deletes(): void
    {
        $company = $this->makeCompany(1);
        $this->actingAsSuperAdmin();
        $invoice = Invoice::factory()->create([
            'tenant_id'  => 1,
            'company_id' => $company->id,
            'number'     => 'INV-FORCE-002',
        ]);
        $invoice->delete();

        $response = $this->delete(route('business_management.invoices.force_delete', $invoice->slug), [
            'name_confirmation' => 'INV-FORCE-002',
            'reason'            => 'Eliminacion definitiva por cierre fiscal.',
        ]);

        $response->assertRedirect();
        $this->assertNull(Invoice::withTrashed()->find($invoice->id), 'El invoice debe estar hard-deleted.');
    }
}
