<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One-off seeder para crear activities demo en deals ya existentes.
 * Idempotente: skip si el deal ya tiene activities.
 */
class DemoActivitiesSeeder extends Seeder
{
    public function run(): void
    {
        $deals = Deal::where('status', 'open')->get();

        foreach ($deals as $deal) {
            // Idempotencia: si ya tiene activities, skip
            $hasActivities = Activity::where('activitable_type', Deal::class)
                ->where('activitable_id', $deal->id)
                ->exists();
            if ($hasActivities) continue;

            $admin = User::where('tenant_id', $deal->tenant_id)->first();
            if (!$admin) continue;

            $common = [
                'activitable_type' => Deal::class,
                'activitable_id'   => $deal->id,
                'tenant_id'        => $deal->tenant_id,
                'actor_user_id'    => $admin->id,
                'created_by'       => $admin->id,
            ];

            Activity::create($common + [
                'type'         => 'note',
                'body'         => 'Primer contacto vía LinkedIn. Mostraron interés en agendar demo.',
                'completed_at' => now()->subDays(10),
            ]);
            Activity::create($common + [
                'type'         => 'call',
                'subject'      => 'Llamada de calificación',
                'body'         => 'Habla con el área comercial. Tienen presupuesto asignado para este año.',
                'outcome'      => 'answered',
                'duration_min' => 18,
                'completed_at' => now()->subDays(7),
            ]);
            Activity::create($common + [
                'type'         => 'email',
                'subject'      => 'Propuesta inicial enviada',
                'body'         => "Hola equipo,\n\nAdjunto la propuesta inicial con la versión Enterprise. Cualquier consulta me dicen.\n\nSaludos.",
                'completed_at' => now()->subDays(5),
            ]);
            Activity::create($common + [
                'type'         => 'meeting',
                'subject'      => 'Demo técnica con el equipo',
                'body'         => 'Demostrar funcionalidad de reportes y permisos por workspace.',
                'location'     => 'https://meet.google.com/abc-demo',
                'due_at'       => now()->addDays(3)->setTime(10, 0),
                'duration_min' => 45,
            ]);
            Activity::create($common + [
                'type'     => 'task',
                'subject'  => 'Enviar contrato firmado',
                'body'     => 'Pendiente la firma del CEO. Follow-up el viernes.',
                'due_at'   => now()->addDays(5)->setTime(17, 0),
                'priority' => 'high',
            ]);
        }

        $this->command?->info('Demo activities seeded for ' . $deals->count() . ' open deals.');
    }
}
