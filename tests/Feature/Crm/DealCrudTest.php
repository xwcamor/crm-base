<?php

namespace Tests\Feature\Crm;

use App\Models\Deal;

class DealCrudTest extends DealTestCase
{
    public function test_admin_sees_only_deals_of_his_tenant(): void
    {
        $p1 = $this->makePipeline(1);
        $s1 = $this->makeStage($p1->id, 1);
        $p2 = $this->makePipeline(2);
        $s2 = $this->makeStage($p2->id, 2);

        Deal::factory()->create(['tenant_id' => 1, 'name' => 'Deal T1', 'pipeline_id' => $p1->id, 'stage_id' => $s1->id, 'status' => 'open']);
        Deal::factory()->create(['tenant_id' => 2, 'name' => 'Deal T2', 'pipeline_id' => $p2->id, 'stage_id' => $s2->id, 'status' => 'open']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('crm.deals.index'));
        $response->assertOk();

        $visible = Deal::query()->pluck('name')->all();
        $this->assertContains('Deal T1', $visible);
        $this->assertNotContains('Deal T2', $visible);
    }

    public function test_company_id_required_on_create(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $payload = $this->dealPayload(['owner_id' => $user->id]);
        unset($payload['company_id']);

        $response = $this->post(route('crm.deals.store'), $payload);
        $response->assertSessionHasErrors('company_id');
    }

    public function test_currency_code_required_on_create(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $payload = $this->dealPayload(['owner_id' => $user->id]);
        unset($payload['currency_code']);

        $response = $this->post(route('crm.deals.store'), $payload);
        $response->assertSessionHasErrors('currency_code');
    }

    public function test_pipeline_id_and_stage_id_required(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $payload = $this->dealPayload(['owner_id' => $user->id]);
        unset($payload['pipeline_id'], $payload['stage_id']);

        $response = $this->post(route('crm.deals.store'), $payload);
        $response->assertSessionHasErrors(['pipeline_id', 'stage_id']);
    }

    public function test_owner_id_required(): void
    {
        $this->actingAsTenantAdmin(1);
        $payload = $this->dealPayload();
        unset($payload['owner_id']);

        $response = $this->post(route('crm.deals.store'), $payload);
        $response->assertSessionHasErrors('owner_id');
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $p = $this->makePipeline(1);
        $s = $this->makeStage($p->id, 1);
        $deal = Deal::factory()->create([
            'tenant_id'   => 1,
            'name'        => 'To Delete Deal',
            'pipeline_id' => $p->id,
            'stage_id'    => $s->id,
            'status'      => 'open',
        ]);

        $response = $this->delete(route('crm.deals.deleteSave', $deal->slug), [
            'deleted_description' => 'Cancelado por el cliente.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
    }

    /**
     * Cuando se crea un deal en un stage con probability_pct definida, el
     * deal hereda esa probabilidad (deal.probability_pct == stage.probability_pct).
     * Implementado en DealService::create — setea probability_pct desde el
     * stage si el request no la trae explicita.
     */
    public function test_probability_auto_inherits_from_stage_on_create(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $pipeline = $this->makePipeline(1);
        $stage = $this->makeStage($pipeline->id, 1, ['probability_pct' => 75]);
        $company = $this->makeCompany(1);

        $response = $this->post(route('crm.deals.store'), [
            'name'          => 'Deal con prob auto',
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stage->id,
            'status'        => 'open',
            'value'         => 5000,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => $user->id,
            'is_active'     => true,
            // Notar: NO mando probability_pct — debe heredarse del stage.
        ]);

        $response->assertRedirect();
        $deal = Deal::where('name', 'Deal con prob auto')->first();
        $this->assertNotNull($deal, 'Deal debe haberse creado.');
        $this->assertSame(75, (int) $deal->probability_pct,
            'probability_pct debe heredarse del stage (75) cuando no se pasa explicito.');
    }

    /**
     * Workflow won: cuando el deal pasa a status=won, won_at debe quedar
     * seteado (timestamp). Implementado en DealService::update via helper
     * applyStatusTimestamps.
     */
    public function test_won_deal_status_workflow(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $pipeline = $this->makePipeline(1);
        $stage    = $this->makeStage($pipeline->id, 1);
        $stageWon = $this->makeStage($pipeline->id, 1, [
            'name' => 'Closed Won', 'is_won' => true, 'probability_pct' => 100,
        ]);
        $company  = $this->makeCompany(1);

        $deal = Deal::factory()->create([
            'tenant_id'     => 1,
            'name'          => 'Deal a ganar',
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stage->id,
            'status'        => 'open',
            'value'         => 9999,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => $user->id,
        ]);

        $response = $this->put(route('crm.deals.update', $deal->slug), [
            'name'          => $deal->name,
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stageWon->id,
            'status'        => 'won',
            'value'         => 9999,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => $user->id,
            'is_active'     => true,
        ]);

        $response->assertRedirect();
        $fresh = $deal->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->won_at,
            'won_at debe quedar seteado cuando el status pasa a won.');
    }

    /**
     * Workflow lost: cuando el deal pasa a status=lost, el motivo
     * (lost_reason_note O lost_reason_source_id) debe quedar registrado.
     * Implementado en UpdateDealRequest::withValidator via sometimes
     * (required_without al settear status=lost).
     */
    public function test_lost_deal_requires_lost_reason(): void
    {
        $user = $this->actingAsTenantAdmin(1);
        $pipeline = $this->makePipeline(1);
        $stage     = $this->makeStage($pipeline->id, 1);
        $stageLost = $this->makeStage($pipeline->id, 1, [
            'name' => 'Closed Lost', 'is_lost' => true, 'probability_pct' => 0,
        ]);
        $company   = $this->makeCompany(1);

        $deal = Deal::factory()->create([
            'tenant_id'     => 1,
            'name'          => 'Deal a perder',
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stage->id,
            'status'        => 'open',
            'value'         => 1500,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => $user->id,
        ]);

        // Update a status=lost SIN motivo — debe rechazarse.
        $response = $this->put(route('crm.deals.update', $deal->slug), [
            'name'          => $deal->name,
            'pipeline_id'   => $pipeline->id,
            'stage_id'      => $stageLost->id,
            'status'        => 'lost',
            'value'         => 1500,
            'currency_code' => 'USD',
            'company_id'    => $company->id,
            'owner_id'      => $user->id,
            'is_active'     => true,
            // Notar: no mando lost_reason_note ni lost_reason_source_id.
        ]);

        $response->assertSessionHasErrors(['lost_reason_note']);
    }
}
