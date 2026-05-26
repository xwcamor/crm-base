<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PDO;

#[AsCommand(
    name: 'setup:project',
    description: 'Initialize the system: recreate the database (or refresh tables), run migrations and seeders.'
)]
class SetupProjectCommand extends Command
{
    protected $signature = 'setup:project {--allow-production : Explicitly allow running on APP_ENV=production (DESTRUCTIVE: drops all tables). Use only for the very first setup of a fresh prod database.}';

    public function handle(): int
    {
        // Hard guard: this command is destructive (drops all tables).
        // En prod requiere --allow-production explicito + 2 confirmaciones.
        // Caso de uso: el primer setup de una DB vacia en un droplet recien
        // creado. Despues de ese setup inicial, futuras actualizaciones deben
        // usar `php artisan migrate --force` (sin perder data).
        if (app()->environment('production')) {
            if (!$this->option('allow-production')) {
                $this->error('Refusing to run setup:project on APP_ENV=production.');
                $this->line('  - Para actualizaciones de prod usar: php artisan migrate --force');
                $this->line('  - Solo para el setup INICIAL de una DB vacia, pasar el flag: --allow-production');
                return self::FAILURE;
            }

            $this->warn('================================================================');
            $this->warn('  PRODUCTION ENVIRONMENT DETECTED');
            $this->warn('  This command will DROP ALL TABLES y recrear la DB desde cero.');
            $this->warn('  Toda la data de produccion sera DESTRUIDA permanentemente.');
            $this->warn('================================================================');

            if (!$this->confirm('Estas ABSOLUTAMENTE seguro de querer continuar?', false)) {
                $this->warn('Task cancelled.');
                return self::SUCCESS;
            }

            $expected = 'borrar todo';
            $typed = $this->ask("Confirmacion final: escribe '{$expected}' (sin comillas) para proceder");
            if (trim((string) $typed) !== $expected) {
                $this->error('Texto incorrecto. Task cancelled.');
                return self::SUCCESS;
            }
        }

        $connection = Config::get('database.default');
        $cfg        = Config::get("database.connections.{$connection}");
        $dbName     = $cfg['database'] ?? null;

        if (! $dbName) {
            $this->error("No database configured for connection [{$connection}].");
            return self::FAILURE;
        }

        $env = app()->environment();
        $this->warn("Environment: [{$env}] | Driver: [{$connection}] | DB: [{$dbName}]");
        $this->warn("This will DROP ALL TABLES and re-run migrations + seeders.");

        if (! $this->confirm("Are you sure you want to continue?")) {
            $this->warn('Task cancelled.');
            return self::SUCCESS;
        }

        match ($connection) {
            'mysql' => $this->recreateMysql($cfg),
            'pgsql' => $this->info("Postgres detected — skipping DROP DATABASE (requires superuser). Using migrate:fresh instead."),
            default => $this->warn("Driver [{$connection}] not specifically handled — relying on migrate:fresh."),
        };

        $this->info("Running migrate:fresh --seed ...");
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->info(Artisan::output());

        // Demo data — solo en entornos no-prod (este comando ya bloquea prod
        // arriba). Cada Demo*Seeder es idempotente y skipea si el tenant ya
        // tiene datos del modulo correspondiente. Se mantienen separados de
        // DatabaseSeeder para que `php artisan db:seed` jamas pueda crear
        // data de prueba si alguien lo corre manualmente en prod.
        $demoSeeders = [
            'Database\\Seeders\\DemoProductsSeeder',
            'Database\\Seeders\\DemoCrmSeeder',
            'Database\\Seeders\\DemoSalesSeeder',
            'Database\\Seeders\\DemoOperationsSeeder',
            'Database\\Seeders\\DemoExtrasSeeder',
            'Database\\Seeders\\DemoActivitiesSeeder',
            'Database\\Seeders\\DemoMessagesSeeder',
            'Database\\Seeders\\DemoAutomationsSeeder',
        ];

        $this->info("Running demo seeders ...");
        foreach ($demoSeeders as $seeder) {
            $short = class_basename($seeder);
            $this->info("  -> {$short}");
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->line($output);
            }
        }

        $this->info("Project successfully initialized.");
        return self::SUCCESS;
    }

    private function recreateMysql(array $cfg): void
    {
        try {
            $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']}", $cfg['username'], $cfg['password']);
            $pdo->exec("DROP DATABASE IF EXISTS `{$cfg['database']}`;");
            $this->info("Database `{$cfg['database']}` dropped.");
            $pdo->exec("CREATE DATABASE `{$cfg['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->info("Database `{$cfg['database']}` created.");
        } catch (\Exception $e) {
            $this->error("Error recreating MySQL database: " . $e->getMessage());
        }
    }
}
