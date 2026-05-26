<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo automations — crea 2 automations de ejemplo por cada tenant operativo
 * (Empresa 1, Empresa 2). Se crean DESACTIVADAS (is_active=false) a proposito:
 * un seeder no deberia disparar jobs reales en ningun entorno. Sirven para
 * que la pagina de Automations no aparezca vacia y el usuario pueda editarlas
 * y activarlas cuando quiera.
 *
 * Idempotente: skip por tenant si ya tiene automations.
 */
class DemoAutomationsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();

        foreach ($tenants as $tenant) {
            if (Automation::where('tenant_id', $tenant->id)->exists()) {
                $this->command?->warn("  - {$tenant->name}: ya tiene automations, salteado.");
                continue;
            }

            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->orderBy('id')
                ->first();

            if (!$admin) {
                $this->command?->warn("  - {$tenant->name}: sin admin, salteado.");
                continue;
            }

            Automation::create([
                'tenant_id'      => $tenant->id,
                'name'           => 'Resumen diario de clientes',
                'description'    => 'Cada manana a las 9am, envia notificacion in-app a los admins con un resumen de clientes activos.',
                'is_active'      => false,
                'trigger_type'   => 'schedule',
                'trigger_config' => ['kind' => 'daily', 'time' => '09:00'],
                'data_source'    => 'customers',
                'data_filter'    => ['where' => [['field' => 'is_active', 'op' => '=', 'value' => true]], 'limit' => 100],
                'action_type'    => 'in_app_notification',
                'action_config'  => [
                    'recipients' => 'tenant_admins',
                    'title'      => 'Resumen diario: {count} clientes activos',
                    'body'       => 'Hola, hay {count} clientes activos en el sistema al {date}. Revisalos en la seccion de CRM.',
                ],
                'next_run_at'    => now()->addDay()->setTime(9, 0),
                'created_by'     => $admin->id,
            ]);

            Automation::create([
                'tenant_id'      => $tenant->id,
                'name'           => 'Alerta semanal de suscripciones por vencer',
                'description'    => 'Cada lunes a las 8am, notifica a admins de suscripciones que vencen en los proximos 7 dias.',
                'is_active'      => false,
                'trigger_type'   => 'schedule',
                'trigger_config' => ['kind' => 'weekly', 'day' => 'monday', 'time' => '08:00'],
                'data_source'    => 'subscriptions',
                'data_filter'    => ['where' => [], 'limit' => 50],
                'action_type'    => 'in_app_notification',
                'action_config'  => [
                    'recipients' => 'tenant_admins',
                    'title'      => 'Suscripciones por vencer: {count}',
                    'body'       => 'Hay {count} suscripciones que vencen en los proximos 7 dias. Revisa en la seccion de Suscripciones.',
                ],
                'next_run_at'    => now()->next('Monday')->setTime(8, 0),
                'created_by'     => $admin->id,
            ]);

            $this->command?->info("  - {$tenant->name}: 2 automations demo creadas (DESACTIVADAS).");
        }
    }
}
