<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Payment;

/**
 * Papelera + restore + force-delete de Payments.
 *
 * Bloque trash/restore/force_delete gateado por role:super.
 */
class PaymentTrashTest extends PaymentTestCase
{
    public function test_super_can_access_trash(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('business_management.payments.trash'));
        $response->assertOk();
    }

    public function test_admin_cannot_access_trash(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->get(route('business_management.payments.trash'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_can_restore(): void
    {
        $method = $this->makePaymentMethod(1);
        $this->actingAsSuperAdmin();
        $payment = Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'reference'         => 'PAY-RESTORE-001',
        ]);
        $payment->delete();

        $response = $this->post(route('business_management.payments.restore', $payment->slug));

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'id'         => $payment->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_requires_reference_match(): void
    {
        $method = $this->makePaymentMethod(1);
        $this->actingAsSuperAdmin();
        $payment = Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'reference'         => 'PAY-FORCE-001',
        ]);
        $payment->delete();

        $response = $this->delete(route('business_management.payments.force_delete', $payment->slug), [
            'name_confirmation' => 'PAY-WRONG-999',
            'reason'            => 'Eliminacion definitiva por reverso confirmado.',
        ]);

        $response->assertSessionHasErrors('name_confirmation');
        $this->assertNotNull(Payment::withTrashed()->find($payment->id));
    }

    public function test_force_delete_with_correct_reference_hard_deletes(): void
    {
        $method = $this->makePaymentMethod(1);
        $this->actingAsSuperAdmin();
        $payment = Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'reference'         => 'PAY-FORCE-002',
        ]);
        $payment->delete();

        $response = $this->delete(route('business_management.payments.force_delete', $payment->slug), [
            'name_confirmation' => 'PAY-FORCE-002',
            'reason'            => 'Eliminacion definitiva por reverso confirmado.',
        ]);

        $response->assertRedirect();
        $this->assertNull(Payment::withTrashed()->find($payment->id), 'El payment debe estar hard-deleted.');
    }
}
