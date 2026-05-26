<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Invoice;
use App\Models\Payment;

class PaymentCrudTest extends PaymentTestCase
{
    public function test_admin_sees_only_payments_of_his_tenant(): void
    {
        $methodT1 = $this->makePaymentMethod(1);
        $methodT2 = $this->makePaymentMethod(2);

        Payment::factory()->create(['tenant_id' => 1, 'payment_method_id' => $methodT1->id, 'reference' => 'PAY-T1-0001']);
        Payment::factory()->create(['tenant_id' => 2, 'payment_method_id' => $methodT2->id, 'reference' => 'PAY-T2-0001']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.payments.index'));
        $response->assertOk();

        $visible = Payment::query()->pluck('reference')->all();
        $this->assertContains('PAY-T1-0001', $visible);
        $this->assertNotContains('PAY-T2-0001', $visible);
    }

    public function test_admin_can_create_payment_linked_to_invoice(): void
    {
        $invoice = $this->makeInvoice(1);
        $method  = $this->makePaymentMethod(1);
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('business_management.payments.store'), [
            'reference'         => 'PAY-2026-0042',
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 250.50,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'completed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'reference'  => 'PAY-2026-0042',
            'invoice_id' => $invoice->id,
            'tenant_id'  => 1,
            'amount'     => 250.50,
        ]);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        // NOTA — al momento de escribir este test, NO existe ni una validacion
        // Rule::unique en el controller ni un indice partial unique en la
        // migration para payments.reference. Este test documenta el contrato
        // esperado (unicidad per-tenant) y va a fallar hasta que se agregue
        // el constraint. Cuando se agregue, pasara sin cambios.
        $method = $this->makePaymentMethod(1);
        Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'reference'         => 'PAY-DUP-001',
        ]);

        $invoice = $this->makeInvoice(1);
        $this->actingAsTenantAdmin(1);

        $response = $this->post(route('business_management.payments.store'), [
            'reference'         => 'PAY-DUP-001',
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 100,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'completed',
        ]);

        // Esperamos error de validacion en `reference`.
        $response->assertSessionHasErrors('reference');
    }

    public function test_payment_updates_invoice_balance_due(): void
    {
        $invoice = $this->makeInvoice(1, [
            'grand_total' => 500.00,
            'amount_paid' => 0,
            'balance_due' => 500.00,
            'status'      => 'sent',
        ]);
        $method = $this->makePaymentMethod(1);
        $this->actingAsTenantAdmin(1);

        // Pago parcial 200 — el controller PaymentController::recomputeInvoice
        // debe actualizar amount_paid=200, balance_due=300, status=partial.
        $this->post(route('business_management.payments.store'), [
            'reference'         => 'PAY-RECOMP-001',
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 200,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'completed',
        ]);

        $fresh = $invoice->fresh();
        $this->assertEquals(200.00, (float) $fresh->amount_paid);
        $this->assertEquals(300.00, (float) $fresh->balance_due);
        $this->assertSame('partial', $fresh->status);

        // Pago que completa el balance — status pasa a 'paid'.
        $this->post(route('business_management.payments.store'), [
            'reference'         => 'PAY-RECOMP-002',
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 300,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'completed',
        ]);

        $fresh = $invoice->fresh();
        $this->assertEquals(500.00, (float) $fresh->amount_paid);
        $this->assertEquals(0.00, (float) $fresh->balance_due);
        $this->assertSame('paid', $fresh->status);
    }

    public function test_payment_status_workflow(): void
    {
        // pending -> completed -> refunded via update.
        $invoice = $this->makeInvoice(1);
        $method  = $this->makePaymentMethod(1);
        $this->actingAsTenantAdmin(1);

        $payment = Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'invoice_id'        => $invoice->id,
            'status'            => 'pending',
            'amount'            => 100,
            'reference'         => 'PAY-WF-001',
        ]);

        // pending -> completed
        $r1 = $this->put(route('business_management.payments.update', $payment->slug), [
            'reference'         => $payment->reference,
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 100,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'completed',
        ]);
        $r1->assertRedirect();
        $this->assertSame('completed', $payment->fresh()->status);

        // completed -> refunded
        $r2 = $this->put(route('business_management.payments.update', $payment->slug), [
            'reference'         => $payment->reference,
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => 100,
            'currency_code'     => 'USD',
            'paid_at'           => now()->toDateTimeString(),
            'status'            => 'refunded',
        ]);
        $r2->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_soft_delete_with_reason(): void
    {
        $method = $this->makePaymentMethod(1);
        $this->actingAsTenantAdmin(1);
        $payment = Payment::factory()->create([
            'tenant_id'         => 1,
            'payment_method_id' => $method->id,
            'reference'         => 'PAY-DEL-001',
        ]);

        $response = $this->delete(route('business_management.payments.deleteSave', $payment->slug), [
            'deleted_description' => 'Pago anulado por reverso bancario.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }
}
