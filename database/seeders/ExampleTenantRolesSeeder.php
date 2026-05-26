<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * ExampleTenantRolesSeeder — roles custom de demostración en Empresa 1 y 2.
 *
 * Crea dos perfiles tenant-scoped por workspace para mostrar el flujo de
 * delegación que un admin haría en su día a día, y los asigna a los workers
 * existentes para validar el sistema de permisos visualmente.
 *
 *   Empresa 1 (id=1):  jose → Customer Editor   |  pedro → Customer Viewer
 *   Empresa 2 (id=2):  luis → Customer Editor   |  ana   → Customer Viewer
 *
 * Permisos por perfil:
 *   Customer Editor: customers.view, customers.show, customers.create,
 *                    customers.edit, customers.delete
 *   Customer Viewer: customers.view, customers.show
 *
 * NOTA de plan: ambos workspaces tienen un plan con `team_management` activo
 * (Empresa 1 enterprise, Empresa 2 pro), así que sus admins pueden gestionar
 * Users + Roles. Si bajaran a free/basic, estos perfiles seguirían en DB pero
 * el módulo Roles quedaría oculto por el gate de plan.
 *
 * Idempotente: usa firstOrCreate + syncPermissions para re-ejecutarse limpio.
 *
 * Requiere: SystemModulesSeeder + RolesAndPermissionsSeeder corridos antes,
 * para que los permisos canónicos `customers.*` existan.
 */
class ExampleTenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Limpieza de roles viejos de versiones previas del seeder.
        $legacyNames = ['Reception', 'Accountant', 'Soporte', 'Editor', 'Visitante'];
        $legacy = Role::whereIn('name', $legacyNames)->whereIn('tenant_id', [1, 2])->get();
        foreach ($legacy as $r) {
            DB::table('role_has_permissions')->where('role_id', $r->id)->delete();
            DB::table('model_has_roles')->where('role_id', $r->id)->delete();
            $r->delete();
        }
        if ($legacy->isNotEmpty()) {
            $this->command?->info('Roles viejos eliminados: ' . $legacy->pluck('name')->implode(', '));
        }

        $editorPerms = Permission::whereIn('name', [
            'customers.view', 'customers.show', 'customers.create', 'customers.edit', 'customers.delete',
        ])->where('guard_name', 'web')->get();

        $viewerPerms = Permission::whereIn('name', [
            'customers.view', 'customers.show',
        ])->where('guard_name', 'web')->get();

        // Por cada workspace: 2 perfiles + asignación a sus 2 workers.
        $workspaces = [
            1 => ['editor' => 'jose@gmail.com', 'viewer' => 'pedro@gmail.com'],
            2 => ['editor' => 'luis@gmail.com', 'viewer' => 'ana@gmail.com'],
        ];

        foreach ($workspaces as $tenantId => $workers) {
            // ── Customer Editor ────────────────────────────────────────────
            $editor = Role::firstOrCreate(
                ['name' => 'Customer Editor', 'guard_name' => 'web', 'tenant_id' => $tenantId],
                ['description' => 'Gestión completa de customers: ver, crear, editar, eliminar.', 'is_active' => true],
            );
            $editor->update([
                'description' => 'Gestión completa de customers: ver, crear, editar, eliminar.',
                'is_active'   => true,
            ]);
            $editor->syncPermissions($editorPerms);

            // ── Customer Viewer ────────────────────────────────────────────
            $viewer = Role::firstOrCreate(
                ['name' => 'Customer Viewer', 'guard_name' => 'web', 'tenant_id' => $tenantId],
                ['description' => 'Solo lectura de customers.', 'is_active' => true],
            );
            $viewer->update([
                'description' => 'Solo lectura de customers.',
                'is_active'   => true,
            ]);
            $viewer->syncPermissions($viewerPerms);

            $this->command?->info("Tenant {$tenantId}: 'Customer Editor' ({$editorPerms->count()} perms) + 'Customer Viewer' ({$viewerPerms->count()} perms)");

            // ── Asignación a workers ───────────────────────────────────────
            foreach (['editor' => $editor, 'viewer' => $viewer] as $key => $role) {
                $email = $workers[$key];
                $user  = User::withoutGlobalScopes()->where('email', $email)->first();
                if (!$user) {
                    $this->command?->warn("  · {$email}  NOT FOUND — run UsersSeeder primero.");
                    continue;
                }
                $user->syncRoles([$role]);
                $this->command?->info("  · {$email}  →  {$role->name}");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
