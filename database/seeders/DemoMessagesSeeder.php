<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Communication\MessageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo messages — el super genera 3 anuncios de ejemplo cubriendo los 3
 * audience_type (global, tenant, user) para que el inbox del header tenga
 * contenido visible en demo. Llama a MessageService::publish() para
 * materializar los recipients igual que en runtime.
 *
 * Idempotente: skip si ya hay messages.
 */
class DemoMessagesSeeder extends Seeder
{
    public function run(): void
    {
        if (Message::query()->exists()) {
            $this->command?->warn('  - DemoMessagesSeeder: ya hay messages, salteado.');
            return;
        }

        $super = User::withoutGlobalScopes()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super'))
            ->orderBy('id')
            ->first();

        if (!$super) {
            $this->command?->warn('  - DemoMessagesSeeder: no hay user super, salteado.');
            return;
        }

        $service = app(MessageService::class);

        // 1) Global welcome — todos los humanos lo ven.
        $welcome = Message::create([
            'slug'           => Str::random(22),
            'subject'        => 'Bienvenidos a la plataforma',
            'body'           => '<p>Hola a todos. Esta es la plataforma de gestion de tu empresa. Pasen por las secciones de CRM, Negocio y Reportes para explorar.</p>',
            'created_by'     => $super->id,
            'audience_type'  => Message::AUDIENCE_GLOBAL,
            'audience_id'    => null,
            'allow_replies'  => true,
            'is_active'      => true,
            'published_at'   => now()->subDays(7),
        ]);
        $service->publish($welcome);

        // 2) Per-tenant heads-up — solo Empresa 1.
        $empresa1 = Tenant::where('name', 'Empresa 1')->first();
        if ($empresa1) {
            $tenantMsg = Message::create([
                'slug'           => Str::random(22),
                'subject'        => 'Recordatorio: cierre mensual el viernes',
                'body'           => '<p>Equipo de Empresa 1, no olviden cerrar las cotizaciones pendientes antes del viernes para el reporte mensual.</p>',
                'created_by'     => $super->id,
                'audience_type'  => Message::AUDIENCE_TENANT,
                'audience_id'    => $empresa1->id,
                'allow_replies'  => false,
                'is_active'      => true,
                'published_at'   => now()->subDays(2),
            ]);
            $service->publish($tenantMsg);
        }

        // 3) Direct message — al primer admin humano del primer tenant.
        $admin = User::withoutGlobalScopes()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->whereNotIn('email', function ($q) { $q->select('email')->from('users')->where('email', 'like', 'api+%'); })
            ->whereNotIn('email', function ($q) { $q->select('email')->from('users')->where('email', 'like', 'system+%'); })
            ->orderBy('id')
            ->first();
        if ($admin) {
            $userMsg = Message::create([
                'slug'           => Str::random(22),
                'subject'        => 'Tu cuenta esta lista',
                'body'           => '<p>Hola ' . e($admin->name) . ', tu cuenta de admin ya esta configurada. Si necesitas mas permisos o crear nuevos usuarios, contactame.</p>',
                'created_by'     => $super->id,
                'audience_type'  => Message::AUDIENCE_USER,
                'audience_id'    => $admin->id,
                'allow_replies'  => true,
                'is_active'      => true,
                'published_at'   => now()->subDay(),
            ]);
            $service->publish($userMsg);
        }

        $this->command?->info('  - DemoMessagesSeeder: 3 messages publicados (global, tenant, user).');
    }
}
