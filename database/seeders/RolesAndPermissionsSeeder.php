<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Common actions per module — fuente única en el Observer.
        $actions = \App\Observers\SystemModuleObserver::CANONICAL_ACTIONS;

        // Read modules from system_modules table
        $modules = DB::table('system_modules')->whereNull('deleted_at')->get();

        // Generate permissions dynamically
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$module->permission_key}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // ─── Roles ────────────────────────────────────────────────────────
        $superAdmin = Role::updateOrCreate(
            ['name' => 'super', 'guard_name' => 'web'],
            ['description' => 'Acceso total al sistema (bypass via Gate::before)']
        );

        $admin = Role::updateOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['description' => 'Administrador de cliente']
        );

        // 'api' role — assigned to the invisible system user that holds API tokens.
        // Permissions are NOT attached at the role level here because each token
        // carries its own ability list (Sanctum abilities). The role just lets us
        // identify and hide these users in lists.
        Role::updateOrCreate(
            ['name' => 'api', 'guard_name' => 'web'],
            ['description' => 'Usuario interno para tokens de API (no logueable)']
        );

        // super: Gate::before bypass + sync all (consistency con policy checks).
        $superAdmin->syncPermissions(Permission::all());

        // admin: TODOS los permisos del sistema. Los módulos core (tenants, regions,
        // languages, etc.) no generan permissions a propósito → admin nunca puede
        // siquiera intentar asignarlos a sus roles. Ver SystemModulesSeeder.
        $admin->syncPermissions(Permission::all());

        // ─── Assign roles to seeded users by email ────────────────────────
        // Workers (jose/pedro/luis/ana) quedan SIN rol por diseño. El admin de
        // cada tenant les asigna un perfil custom (Soporte/Editor/Visitante,
        // ver ExampleTenantRolesSeeder) cuando arme su equipo.
        $assignments = [
            'carlos@gmail.com'        => $superAdmin,  // platform owner (alineado con UsersSeeder)
            'antonio@gmail.com'       => $admin,        // Empresa 1 admin
            'hector@gmail.com'        => $admin,        // Empresa 2 admin
            'independiente@gmail.com'     => $admin,        // Independiente (admin de su propio workspace)
        ];

        foreach ($assignments as $email => $role) {
            $userModel = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($userModel) {
                $userModel->syncRoles([$role]);
                $this->command?->info("  · {$email}  →  {$role->name}");
            } else {
                $this->command?->warn("  · {$email}  NOT FOUND (run UsersSeeder first)");
            }
        }

        $this->command?->info('Permissions: ' . Permission::count() . '. Roles: ' . Role::count() . '.');
    }
}
