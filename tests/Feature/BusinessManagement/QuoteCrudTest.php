<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Quote;

class QuoteCrudTest extends QuoteTestCase
{
    public function test_admin_sees_only_quotes_of_his_tenant(): void
    {
        Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-T1-A']);
        Quote::factory()->create(['tenant_id' => 2, 'company_id' => 2, 'reference' => 'COT-T2-B']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('business_management.quotes.index'));
        $response->assertOk();

        $visible = Quote::query()->pluck('reference')->all();
        $this->assertContains('COT-T1-A', $visible);
        $this->assertNotContains('COT-T2-B', $visible);
    }

    public function test_admin_can_create_quote(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.quotes.store'),
            $this->validQuotePayload(['reference' => 'COT-NEW-001'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('quotes', [
            'reference' => 'COT-NEW-001',
            'tenant_id' => 1,
        ]);
        $quote = Quote::where('reference', 'COT-NEW-001')->firstOrFail();
        $this->assertCount(1, $quote->items);
        $this->assertEqualsWithDelta(250.00, (float) $quote->grand_total, 0.01);
    }

    public function test_reference_must_be_unique_within_tenant(): void
    {
        // El controller de Quote NO valida unicidad explicitamente en
        // validateData() — el partial unique index a nivel BD
        // (quotes_tenant_reference_unique) lo previene. Como sqlite en
        // testing puede tener constraints relajados por la transaccion
        // de RefreshDatabase, verificamos que NO se cree un segundo
        // registro con la misma reference + tenant_id.
        Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-DUP-1',
        ]);

        $this->actingAsTenantAdmin(1);

        try {
            $this->post(
                route('business_management.quotes.store'),
                $this->validQuotePayload(['reference' => 'COT-DUP-1'])
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Esperado en pgsql; sqlite testing puede dejarlo pasar.
        }

        // Asercion estricta: no debe haber 2 quotes activos con esa reference
        // en el mismo tenant.
        $count = Quote::where('tenant_id', 1)
            ->where('reference', 'COT-DUP-1')
            ->count();
        $this->assertLessThanOrEqual(1, $count,
            'No debe permitirse 2 quotes con la misma reference en el mismo tenant.'
        );
    }

    public function test_same_reference_allowed_in_different_tenants(): void
    {
        Quote::factory()->create([
            'tenant_id' => 2, 'company_id' => 2,
            'reference' => 'COT-SHARED-1',
        ]);

        $this->actingAsTenantAdmin(1);
        $this->post(
            route('business_management.quotes.store'),
            $this->validQuotePayload(['reference' => 'COT-SHARED-1'])
        );

        $this->assertDatabaseHas('quotes', ['reference' => 'COT-SHARED-1', 'tenant_id' => 1]);
        $this->assertDatabaseHas('quotes', ['reference' => 'COT-SHARED-1', 'tenant_id' => 2]);
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-DEL',
        ]);

        $response = $this->delete(
            route('business_management.quotes.deleteSave', $quote->slug),
            ['deleted_description' => 'Cotizacion cancelada.']
        );

        $response->assertRedirect();
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_store_quote_returns_to_index(): void
    {
        $this->actingAsTenantAdmin(1);

        $response = $this->post(
            route('business_management.quotes.store'),
            $this->validQuotePayload(['reference' => 'COT-IDX-001'])
        );

        $response->assertRedirect(route('business_management.quotes.index'));
    }

    public function test_update_quote_redirects_to_show(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-UPD',
        ]);

        $response = $this->put(
            route('business_management.quotes.update', $quote->slug),
            $this->validQuotePayload(['reference' => 'COT-UPD-2'])
        );

        $response->assertRedirect(route('business_management.quotes.show', $quote->slug));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'reference' => 'COT-UPD-2']);
    }

    public function test_cannot_save_without_items(): void
    {
        $this->actingAsTenantAdmin(1);

        $payload = $this->validQuotePayload(['reference' => 'COT-NOITEMS']);
        unset($payload['items']);

        $response = $this->post(route('business_management.quotes.store'), $payload);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('quotes', ['reference' => 'COT-NOITEMS']);
    }

    // ── Workflow ────────────────────────────────────────────────────────

    public function test_workflow_send(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-SEND',
            'status'    => 'draft',
        ]);

        $response = $this->post(route('business_management.quotes.send', $quote->slug));

        $response->assertRedirect();
        $fresh = $quote->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
    }

    public function test_workflow_accept(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-ACC',
            'status'    => 'sent',
            'sent_at'   => now(),
        ]);

        $response = $this->post(route('business_management.quotes.accept', $quote->slug), [
            'signed_by_name'  => 'Cliente X',
            'signed_by_email' => 'cliente@example.com',
        ]);

        $response->assertRedirect();
        $fresh = $quote->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertNotNull($fresh->accepted_at);
        $this->assertSame('Cliente X', $fresh->signed_by_name);
    }

    public function test_workflow_reject(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-REJ',
            'status'    => 'sent',
            'sent_at'   => now(),
        ]);

        $response = $this->post(route('business_management.quotes.reject', $quote->slug), [
            'rejected_reason' => 'Precio fuera de presupuesto.',
        ]);

        $response->assertRedirect();
        $fresh = $quote->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertNotNull($fresh->rejected_at);
        $this->assertSame('Precio fuera de presupuesto.', $fresh->rejected_reason);
    }

    public function test_cannot_send_non_draft_quote(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-SENT-ALREADY',
            'status'    => 'sent',
        ]);

        $response = $this->post(route('business_management.quotes.send', $quote->slug));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('sent', $quote->fresh()->status);
    }
}
