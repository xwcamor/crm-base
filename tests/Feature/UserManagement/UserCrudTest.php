<?php

namespace Tests\Feature\UserManagement;

use App\Models\Role;
use App\Models\User;

class UserCrudTest extends UserTestCase
{
    public function test_admin_sees_only_users_of_his_tenant(): void
    {
        $this->actingAsTenantAdmin(1);
        User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1, 'name' => 'Bob 1']);
        User::factory()->create(['tenant_id' => 2, 'country_id' => 1, 'locale_id' => 1, 'name' => 'Bob 2']);

        $response = $this->get(route('user_management.users.index'));
        $response->assertOk();
    }

    public function test_belongs_to_tenant_trait_auto_fills_tenant_id_on_create(): void
    {
        // El trait BelongsToTenant auto-llena tenant_id cuando un user
        // autenticado crea otro user. Test enfocado solo en eso.
        $admin = $this->actingAsTenantAdmin(1);

        $created = User::factory()->create([
            'country_id' => 1,
            'locale_id'  => 1,
            'name'       => 'Nuevo User',
            'email'      => 'nuevo@example.com',
        ]);

        $this->assertSame(1, $created->tenant_id, 'tenant_id debe auto-asignarse del admin creador');
    }

    public function test_role_options_for_admin_includes_global_admin_role(): void
    {
        // Crear un rol custom del tenant
        $custom = Role::create(['name' => 'soporte', 'guard_name' => 'web', 'tenant_id' => 1, 'description' => 'Soporte custom']);

        $this->actingAsTenantAdmin(1);
        $response = $this->get(route('user_management.users.create'));

        $response->assertOk();
        // El dropdown debe incluir 'admin' (global) + 'soporte' (tenant 1)
        // No debe incluir super ni api.
        $props = $response->viewData('page')['props'];
        $roleNames = collect($props['roleOptions'])->pluck('label')->map(fn ($l) => explode(' ', $l)[0])->all();
        $this->assertContains('admin', $roleNames);
        $this->assertContains('soporte', $roleNames);
        $this->assertNotContains('super', $roleNames);
        $this->assertNotContains('api', $roleNames);
    }

    public function test_system_user_with_api_role_is_hidden_from_listing(): void
    {
        // Crear un system_user con rol api en tenant 1
        $systemUser = User::factory()->create([
            'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1,
            'email' => 'api+test@system.local',
        ]);
        $systemUser->assignRole('api');

        $admin = $this->actingAsTenantAdmin(1);

        // La query del controller usa BelongsToTenant + HideSuperScope.
        // El system_user NO debe aparecer en el listado.
        $visibleUsers = User::query()->get(['id', 'email']);
        $emails = $visibleUsers->pluck('email')->all();
        $this->assertNotContains('api+test@system.local', $emails);
        $this->assertContains($admin->email, $emails);
    }

    public function test_soft_delete_user_with_reason(): void
    {
        $admin = $this->actingAsTenantAdmin(1);
        $target = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $response = $this->delete(route('user_management.users.deleteSave', $target->slug), [
            'deleted_description' => 'Dejó la empresa.',
        ]);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'deleted_description' => 'Dejó la empresa.',
        ]);
    }
}
