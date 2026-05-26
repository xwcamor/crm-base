<?php

namespace Tests\Feature\Crm;

use App\Models\Company;
use App\Models\Contact;

class ContactCrudTest extends ContactTestCase
{
    public function test_admin_sees_only_contacts_of_his_tenant(): void
    {
        Contact::factory()->create(['tenant_id' => 1, 'name' => 'Contacto T1']);
        Contact::factory()->create(['tenant_id' => 2, 'name' => 'Contacto T2']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('crm.contacts.index'));
        $response->assertOk();

        $visible = Contact::query()->pluck('name')->all();
        $this->assertContains('Contacto T1', $visible);
        $this->assertNotContains('Contacto T2', $visible);
    }

    public function test_first_or_last_name_required(): void
    {
        $user = $this->actingAsTenantAdmin(1);

        // Sin first_name ni last_name → debe fallar (required_without).
        $response = $this->post(route('crm.contacts.store'), $this->contactPayload([
            'first_name' => '',
            'last_name'  => '',
            'owner_id'   => $user->id,
        ]));

        $response->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_email_or_phone_required(): void
    {
        $user = $this->actingAsTenantAdmin(1);

        // Sin email + sin phone + sin mobile_phone → debe fallar
        // (required_without_all).
        $response = $this->post(route('crm.contacts.store'), $this->contactPayload([
            'primary_email' => '',
            'primary_phone' => '',
            'mobile_phone'  => '',
            'owner_id'      => $user->id,
        ]));

        $response->assertSessionHasErrors(['primary_email', 'primary_phone']);
    }

    /**
     * Regla de negocio enterprise: una company debe tener UN solo contact
     * con is_primary_for_company=true. Pattern auto-demote (HubSpot/Salesforce)
     * implementado en ContactService::create/update: al crear/actualizar un
     * contact marcado como primary, des-marca a cualquier otro primary anterior
     * de la misma company.
     */
    public function test_primary_contact_for_company_unique(): void
    {
        $user = $this->actingAsTenantAdmin(1);

        $company = Company::factory()->create([
            'tenant_id' => 1,
            'name'      => 'Acme Co',
        ]);

        // Primer primary contact — se crea sin conflicto.
        Contact::factory()->create([
            'tenant_id'              => 1,
            'name'                   => 'Primary Original',
            'first_name'             => 'Primary',
            'last_name'              => 'Original',
            'primary_email'          => 'primary.orig@example.com',
            'company_id'             => $company->id,
            'is_primary_for_company' => true,
        ]);

        // Segundo intento de primary — esperado: la app debe rechazarlo
        // (validation error) O des-marcar al anterior. Cubro ambos casos.
        // name explicito para evitar colision con el unique de name por tenant.
        $response = $this->post(route('crm.contacts.store'), $this->contactPayload([
            'name'                   => 'Primary Duplicate',
            'first_name'             => 'Primary',
            'last_name'              => 'Duplicate',
            'primary_email'          => 'primary.dup@example.com',
            'company_id'             => $company->id,
            'is_primary_for_company' => true,
            'owner_id'               => $user->id,
        ]));

        // Verificamos primero que el segundo contact se haya creado (el HTTP
        // store no falla por otros motivos — email distinto, names distintos).
        // Si el response es un redirect Y existe el segundo en DB, entonces
        // el service debio des-marcar el primary anterior.
        $second = Contact::where('primary_email', 'primary.dup@example.com')->first();
        $this->assertNotNull($second,
            'El segundo contact debe haberse creado para que la regla de unicidad de primary sea testeable.');

        // Invariante de negocio: 1 primary por company. Como acabamos de
        // crear un segundo con is_primary_for_company=true, el service o
        // model debe haber des-marcado al primero (auto-demote pattern).
        $primaryCount = Contact::where('company_id', $company->id)
            ->where('is_primary_for_company', true)
            ->count();
        $this->assertSame(1, $primaryCount,
            'Solo debe quedar 1 primary contact por company tras el segundo create.');
    }

    public function test_soft_delete_with_reason(): void
    {
        $this->actingAsTenantAdmin(1);
        $contact = Contact::factory()->create([
            'tenant_id' => 1,
            'name'      => 'To Delete Contact',
        ]);

        $response = $this->delete(route('crm.contacts.deleteSave', $contact->slug), [
            'deleted_description' => 'Ya no es contacto vigente.',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }
}
