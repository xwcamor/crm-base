<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UsersSeeder — usuarios de prueba para desarrollo.
 *
 * Estructura:
 *   - SUPER (Carlos): carlos@gmail.com  (tenant_id = null)
 *
 *   - Empresa 1 (id=1):
 *       admin:  antonio@gmail.com
 *       users:  jose@gmail.com, pedro@gmail.com
 *
 *   - Empresa 2 (id=2):
 *       admin:  hector@gmail.com
 *       users:  luis@gmail.com, ana@gmail.com
 *
 *   - Independiente (id=3):
 *       admin/unico: independiente@gmail.com
 *
 * Passwords default "123456" para dev. Cambiar en produccion.
 * Idempotente: usa updateOrCreate por email.
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Timezones de demo: super (Carlos) en UTC para no depender de
        // ninguna región puntual; el resto en America/Lima como punto de
        // partida regional. Cada user puede sobreescribirlo desde su perfil.
        $users = [
            // Platform owner — super sin tenant, en UTC para ver UTC crudo
            ['email' => 'carlos@gmail.com', 'name' => 'Carlitos', 'tenant_id' => null, 'timezone' => 'UTC'],

            // Empresa 1
            ['email' => 'antonio@gmail.com', 'name' => 'Antonio (Empresa 1 admin)', 'tenant_id' => 1, 'timezone' => 'America/Lima'],
            ['email' => 'jose@gmail.com',  'name' => 'Jose Perez',              'tenant_id' => 1, 'timezone' => 'America/Lima'],
            ['email' => 'pedro@gmail.com', 'name' => 'Pedro Ramirez',           'tenant_id' => 1, 'timezone' => 'America/Lima'],

            // Empresa 2
            ['email' => 'hector@gmail.com', 'name' => 'Hector (Empresa 2 admin)', 'tenant_id' => 2, 'timezone' => 'America/Lima'],
            ['email' => 'luis@gmail.com',  'name' => 'Luis Castro',             'tenant_id' => 2, 'timezone' => 'America/Lima'],
            ['email' => 'ana@gmail.com',   'name' => 'Ana Torres',              'tenant_id' => 2, 'timezone' => 'America/Lima'],

            // Independiente — un solo usuario, sin equipo
            ['email' => 'independiente@gmail.com', 'name' => 'Independiente', 'tenant_id' => 3, 'timezone' => 'America/Lima'],
        ];

        foreach ($users as $data) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'       => $data['name'],
                    'password'   => Hash::make('123456'),
                    'slug'       => User::withoutGlobalScopes()->where('email', $data['email'])->value('slug') ?? Str::random(22),
                    'tenant_id'  => $data['tenant_id'],
                    'country_id' => 1,
                    'locale_id'  => 1,
                    'timezone'   => $data['timezone'],
                    'created_by' => null,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 0) + 1, false)");
        }

        $this->command?->info('Users seeded: ' . count($users) . ' (1 super + 3 admins + 4 workers).');
    }
}
