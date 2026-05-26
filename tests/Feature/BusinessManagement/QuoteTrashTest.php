<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Quote;

/**
 * Papelera + restore + force-delete de Quotes.
 *
 * Trash/restore/force_delete estan gateadas por `role:super` via
 * abort_unless en el controller. Admin del tenant no accede.
 *
 * Force-delete confirma con `name_confirmation` (igual que Customer);
 * el controller compara contra `reference` o `name`, lo que exista.
 */
class QuoteTrashTest extends QuoteTestCase
{
    public function test_super_can_access_trash(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('business_management.quotes.trash'));
        $response->assertOk();
    }

    public function test_admin_cannot_access_trash(): void
    {
        $this->actingAsTenantAdmin(1);

        // El middleware role:super dispara UnauthorizedException;
        // bootstrap/app.php lo convierte en redirect+flash error.
        $response = $this->get(route('business_management.quotes.trash'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_can_restore(): void
    {
        $this->actingAsSuperAdmin();
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-RES',
        ]);
        $quote->delete();

        $response = $this->post(route('business_management.quotes.restore', $quote->slug));

        $response->assertRedirect();
        $this->assertDatabaseHas('quotes', [
            'id'         => $quote->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_requires_name_match(): void
    {
        $this->actingAsSuperAdmin();
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-REAL-1',
        ]);
        $quote->delete();

        $response = $this->delete(route('business_management.quotes.force_delete', $quote->slug), [
            'name_confirmation' => 'COT-WRONG',
            'reason'            => 'Eliminacion definitiva por cierre.',
        ]);

        $response->assertSessionHasErrors('name_confirmation');
        $this->assertNotNull(Quote::withTrashed()->find($quote->id));
    }

    public function test_force_delete_with_correct_name_hard_deletes(): void
    {
        $this->actingAsSuperAdmin();
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-REAL-2',
        ]);
        $quote->delete();

        $response = $this->delete(route('business_management.quotes.force_delete', $quote->slug), [
            'name_confirmation' => 'COT-REAL-2',
            'reason'            => 'Eliminacion definitiva por cierre.',
        ]);

        $response->assertRedirect();
        $this->assertNull(Quote::withTrashed()->find($quote->id), 'El quote debe estar hard-deleted.');
    }

    public function test_bulk_delete_marks_records(): void
    {
        $this->actingAsTenantAdmin(1);

        $a = Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-BD-A']);
        $b = Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-BD-B']);
        $c = Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-BD-C']);

        $response = $this->post(route('business_management.quotes.bulk_delete'), [
            'ids'                 => [$a->id, $b->id, $c->id],
            'deleted_description' => 'Limpieza masiva.',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($a->fresh()->deleted_at);
        $this->assertNotNull($b->fresh()->deleted_at);
        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_bulk_set_active_changes_state(): void
    {
        $this->actingAsTenantAdmin(1);

        $a = Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-BS-A', 'is_active' => true]);
        $b = Quote::factory()->create(['tenant_id' => 1, 'company_id' => 1, 'reference' => 'COT-BS-B', 'is_active' => true]);

        $response = $this->post(route('business_management.quotes.bulk_set_active'), [
            'ids'       => [$a->id, $b->id],
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertFalse((bool) $a->fresh()->is_active);
        $this->assertFalse((bool) $b->fresh()->is_active);
    }

    public function test_undo_last_delete_restores(): void
    {
        $this->actingAsTenantAdmin(1);
        $quote = Quote::factory()->create([
            'tenant_id' => 1, 'company_id' => 1,
            'reference' => 'COT-UNDO',
        ]);

        $this->delete(route('business_management.quotes.deleteSave', $quote->slug), [
            'deleted_description' => 'Eliminacion temporal.',
        ]);
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);

        $response = $this->post(route('business_management.quotes.undo_last_delete'));

        $response->assertRedirect();
        $this->assertDatabaseHas('quotes', [
            'id'         => $quote->id,
            'deleted_at' => null,
        ]);
    }
}
