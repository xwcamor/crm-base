<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Industry;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data para ver el CRM en accion. Crea companies, contacts, pipelines,
 * stages, lead sources y deals realistas para Empresa 1 (Lima) y Empresa 2
 * (norte del Peru: Trujillo/Chiclayo/Piura/Cusco). Ambos tenants operan en PEN.
 *
 * Idempotente — si ya hay deals para un tenant, no hace nada.
 */
class DemoCrmSeeder extends Seeder
{
    /** Pool de nombres unicos por tenant — evita colision con tenant_name_unique_active. */
    protected static array $namePool = [];

    public function run(): void
    {
        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();

        foreach ($tenants as $t) {
            self::$namePool[$t->id] = $this->buildNamePool();
        }

        foreach ($tenants as $tenant) {
            $hasCompanies = Company::where('tenant_id', $tenant->id)->exists();
            $hasDeals     = Deal::where('tenant_id', $tenant->id)->exists();
            if ($hasCompanies || $hasDeals) {
                $this->command?->warn("  · {$tenant->name}: ya tiene data, salteado.");
                continue;
            }

            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('email', function ($q) { $q->select('email')->from('users')->where('email', 'like', 'api+%'); })
                ->orderBy('id')
                ->first();

            if (!$admin) {
                $this->command?->warn("  · {$tenant->name}: sin admin, salteado.");
                continue;
            }

            DB::transaction(fn () => $this->seedForTenant($tenant, $admin));
            $this->command?->info("  · {$tenant->name}: demo CRM creada (admin: {$admin->email})");
        }
    }

    protected function seedForTenant(Tenant $tenant, User $admin): void
    {
        // ─── Lead Sources ─────────────────────────────────────────────────
        $leadSources = collect([
            'Sitio web', 'Referral', 'LinkedIn', 'Evento', 'Cold call', 'Partner',
        ])->map(fn ($name) => LeadSource::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name],
            [
                'slug'       => Str::random(22),
                'created_by' => $admin->id,
                'is_active'  => true,
            ]
        ));

        // ─── Pipeline + Stages (default Sales pipeline) ────────────────────
        $pipeline = Pipeline::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Pipeline de Ventas'],
            [
                'slug'       => Str::random(22),
                'description'=> 'Flujo principal de ventas B2B',
                'color'      => '#1677ff',
                'is_default' => true,
                'sort_order' => 1,
                'created_by' => $admin->id,
                'is_active'  => true,
            ]
        );

        $stagesData = [
            ['Prospección',  '#94a3b8', 1, 10, false, false, 30],
            ['Calificación', '#3b82f6', 2, 25, false, false, 21],
            ['Propuesta',    '#f59e0b', 3, 50, false, false, 14],
            ['Negociación',  '#a855f7', 4, 75, false, false, 10],
            ['Ganada',       '#22c55e', 5, 100, true,  false, 0],
            ['Perdida',      '#ef4444', 6, 0,   false, true,  0],
        ];

        $stages = collect($stagesData)->map(fn ($s) => PipelineStage::firstOrCreate(
            ['pipeline_id' => $pipeline->id, 'name' => $s[0]],
            [
                'slug'            => Str::random(22),
                'color'           => $s[1],
                'sort_order'      => $s[2],
                'probability_pct' => $s[3],
                'is_won'          => $s[4],
                'is_lost'         => $s[5],
                'rot_days'        => $s[6],
                'tenant_id'       => $tenant->id,
                'created_by'      => $admin->id,
                'is_active'       => true,
            ]
        ));

        // ─── Companies + Contacts ──────────────────────────────────────────
        $industryIds = Industry::pluck('id')->all();
        $companiesData = $this->companiesData($tenant);

        $companies = collect();
        foreach ($companiesData as $cd) {
            $company = Company::create(array_merge($cd, [
                'tenant_id'    => $tenant->id,
                'created_by'   => $admin->id,
                'owner_id'     => $admin->id,
                'industry_id'  => $industryIds[array_rand($industryIds)] ?? null,
                'is_active'    => true,
                'preferred_currency_code' => $cd['preferred_currency_code'] ?? null,
            ]));
            $companies->push($company);

            // 1-2 contactos por empresa
            $numContacts = rand(1, 2);
            $contacts = $this->contactsForCompany($company, $numContacts);
            foreach ($contacts as $i => $c) {
                Contact::create(array_merge($c, [
                    'tenant_id'              => $tenant->id,
                    'created_by'             => $admin->id,
                    'owner_id'               => $admin->id,
                    'company_id'             => $company->id,
                    'is_primary_for_company' => $i === 0,
                    'is_active'              => true,
                ]));
            }
        }

        // ─── Deals ─────────────────────────────────────────────────────────
        $dealTemplates = $this->dealTemplates($tenant, $companies, $stages, $leadSources);

        $createdDeals = [];
        foreach ($dealTemplates as $d) {
            $contact = Contact::where('company_id', $d['company_id'])->first();
            $deal = Deal::create(array_merge($d, [
                'tenant_id'      => $tenant->id,
                'created_by'     => $admin->id,
                'owner_id'       => $admin->id,
                'pipeline_id'    => $pipeline->id,
                'contact_id'     => $contact?->id,
                'currency_code'  => $d['currency_code'] ?? $tenant->default_currency_code,
                'is_active'      => true,
            ]));
            $createdDeals[] = $deal;
        }

        // ─── Activities — algunas activities demo por cada deal abierto ────
        // Da volumen al timeline para que el user vea como se ve cuando hay data.
        foreach ($createdDeals as $deal) {
            if ($deal->status !== 'open') continue;
            $this->seedActivitiesForDeal($deal, $admin, $tenant->id);
        }
    }

    protected function seedActivitiesForDeal(Deal $deal, User $admin, int $tenantId): void
    {
        $activities = [
            [
                'type' => 'note',
                'subject' => null,
                'body' => 'Primer contacto vía LinkedIn. Tenían interés en ver una demo.',
                'completed_at' => now()->subDays(10),
            ],
            [
                'type' => 'call',
                'subject' => 'Llamada de calificación',
                'body' => 'Habla con el área comercial. Tienen presupuesto asignado para este año.',
                'outcome' => 'answered',
                'duration_min' => 18,
                'completed_at' => now()->subDays(7),
            ],
            [
                'type' => 'email',
                'subject' => 'Propuesta inicial enviada',
                'body' => "Hola equipo,\n\nAdjunto la propuesta inicial con la versión Enterprise.\nEspero sus comentarios.\n\nSaludos.",
                'completed_at' => now()->subDays(5),
            ],
            [
                'type' => 'meeting',
                'subject' => 'Demo técnica con el equipo',
                'body' => 'Demostrar funcionalidad de reportes y permisos por workspace.',
                'location' => 'https://meet.google.com/abc-demo',
                'due_at' => now()->addDays(3)->setTime(10, 0),
                'duration_min' => 45,
            ],
            [
                'type' => 'task',
                'subject' => 'Enviar contrato firmado',
                'body' => 'Pendiente la firma del CEO. Hacer follow-up el viernes.',
                'due_at' => now()->addDays(5)->setTime(17, 0),
                'priority' => 'high',
            ],
        ];

        foreach ($activities as $a) {
            \App\Models\Activity::create(array_merge($a, [
                'activitable_type' => Deal::class,
                'activitable_id'   => $deal->id,
                'actor_user_id'    => $admin->id,
                'tenant_id'        => $tenantId,
                'created_by'       => $admin->id,
            ]));
        }
    }

    protected function companiesData(Tenant $tenant): array
    {
        $currency = $tenant->default_currency_code;

        // Demo data 100% peruana. Tenant 1 (Lima/capital) y tenant 2 (norte
        // del Perú: Trujillo/Chiclayo/Piura/Cusco) tienen sets distintos para
        // evitar duplicar nombres entre workspaces.
        if ($tenant->id === 1) {
            return [
                ['name' => 'Corporación Andina SAC', 'legal_name' => 'Corporación Andina S.A.C.', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'hot', 'priority' => 'high', 'website' => 'andina.pe', 'annual_revenue' => 2400000, 'employee_count' => 45, 'is_vip' => true, 'account_status' => 'active', 'health_score' => 88, 'churn_risk' => 'low', 'preferred_currency_code' => $currency, 'description' => 'Cliente top — renueva anualmente desde 2022'],
                ['name' => 'Distribuidora Lima Norte', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'warm', 'priority' => 'medium', 'website' => 'limanorte.pe', 'annual_revenue' => 850000, 'employee_count' => 22, 'preferred_currency_code' => $currency, 'account_status' => 'active', 'health_score' => 72, 'churn_risk' => 'low'],
                ['name' => 'TecnoServicios Perú', 'company_type' => 'prospect', 'lifecycle_stage' => 'opportunity', 'rating' => 'hot', 'priority' => 'high', 'website' => 'tecnoserv.pe', 'annual_revenue' => 1200000, 'employee_count' => 35, 'description' => 'En negociación — comparando con Salesforce'],
                ['name' => 'Constructora Vega Hnos.', 'company_type' => 'prospect', 'lifecycle_stage' => 'sql', 'rating' => 'warm', 'priority' => 'medium', 'website' => 'vegahnos.pe', 'employee_count' => 18],
                ['name' => 'Café del Valle EIRL', 'company_type' => 'prospect', 'lifecycle_stage' => 'mql', 'rating' => 'warm', 'priority' => 'low', 'website' => 'cafedelvalle.pe', 'employee_count' => 8],
                ['name' => 'LogiAndes Transportes', 'company_type' => 'customer', 'lifecycle_stage' => 'evangelist', 'rating' => 'hot', 'priority' => 'critical', 'website' => 'logiandes.pe', 'annual_revenue' => 4200000, 'employee_count' => 120, 'is_vip' => true, 'preferred_currency_code' => $currency, 'account_status' => 'active', 'health_score' => 95, 'churn_risk' => 'low', 'description' => 'Promotor activo — nos refirió 3 clientes'],
                ['name' => 'Editorial San Marcos', 'company_type' => 'prospect', 'lifecycle_stage' => 'lead', 'rating' => 'cold', 'priority' => 'low', 'employee_count' => 12],
                ['name' => 'Servicios Mineros del Sur', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'warm', 'priority' => 'high', 'website' => 'mineriasur.pe', 'annual_revenue' => 8500000, 'employee_count' => 250, 'preferred_currency_code' => 'USD', 'account_status' => 'at_risk', 'health_score' => 45, 'churn_risk' => 'high', 'description' => 'Riesgo — no usa el sistema hace 60 días'],
            ];
        }

        // Empresa 2 — operación en el norte/sur del Perú (Trujillo, Chiclayo, Piura, Cusco)
        return [
            ['name' => 'Pesquera Norte SAC', 'legal_name' => 'Pesquera Norte S.A.C.', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'hot', 'priority' => 'high', 'website' => 'pesqueranorte.pe', 'annual_revenue' => 3200000, 'employee_count' => 80, 'is_vip' => true, 'account_status' => 'active', 'health_score' => 91, 'churn_risk' => 'low', 'preferred_currency_code' => $currency],
            ['name' => 'Agroindustrial Trujillo', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'hot', 'priority' => 'medium', 'website' => 'agrotrujillo.pe', 'annual_revenue' => 1800000, 'employee_count' => 65, 'preferred_currency_code' => 'USD'],
            ['name' => 'Textiles Chiclayo SRL', 'company_type' => 'prospect', 'lifecycle_stage' => 'opportunity', 'rating' => 'hot', 'priority' => 'critical', 'website' => 'texchiclayo.pe', 'employee_count' => 180, 'description' => 'Deal grande — propuesta enviada'],
            ['name' => 'Tech Partners Piura', 'company_type' => 'partner', 'lifecycle_stage' => 'customer', 'rating' => 'warm', 'priority' => 'medium', 'website' => 'techpartners.pe', 'employee_count' => 40, 'description' => 'Partner integrador — revende nuestra licencia'],
            ['name' => 'Inmobiliaria del Norte', 'company_type' => 'prospect', 'lifecycle_stage' => 'sql', 'rating' => 'warm', 'priority' => 'low', 'employee_count' => 10],
            ['name' => 'Cafetalera Cajamarca', 'company_type' => 'prospect', 'lifecycle_stage' => 'mql', 'rating' => 'warm', 'priority' => 'low', 'website' => 'cafecajamarca.pe', 'employee_count' => 6],
            ['name' => 'Transportes Cusco Andino', 'company_type' => 'customer', 'lifecycle_stage' => 'customer', 'rating' => 'warm', 'priority' => 'high', 'website' => 'cuscoandino.pe', 'annual_revenue' => 5400000, 'employee_count' => 95, 'preferred_currency_code' => $currency, 'account_status' => 'active', 'health_score' => 78],
            ['name' => 'Artesanías Sicán', 'company_type' => 'prospect', 'lifecycle_stage' => 'lead', 'rating' => 'cold', 'priority' => 'low', 'employee_count' => 5],
        ];
    }

    protected function buildNamePool(): array
    {
        $first = ['Carlos','María','Juan','Lucía','Pedro','Ana','Luis','Sofía','Diego','Valeria','Roberto','Carolina','Andrés','Patricia','Miguel','Daniela','Fernando','Camila','Ricardo','Elena'];
        $last  = ['Pérez','Gómez','Rodríguez','Fernández','Sánchez','López','Vega','Torres','Castro','Mendoza','Ramos','Suárez','Núñez','Aguilar','Salazar'];
        $pool = [];
        foreach ($first as $fn) {
            foreach ($last as $ln) {
                $pool[] = [$fn, $ln];
            }
        }
        shuffle($pool);
        return $pool;
    }

    protected function contactsForCompany(Company $company, int $count): array
    {
        $jobs       = ['CEO', 'Gerente General', 'CFO', 'Gerente de Compras', 'Director de Operaciones', 'Gerente de IT', 'COO'];
        $seniorities = ['c_level', 'director', 'vp', 'manager'];
        $roles      = ['economic_buyer', 'champion', 'influencer', 'technical', 'end_user'];

        $domain = $company->website ?: strtolower(Str::slug($company->name)) . '.com';
        $domain = preg_replace('#^https?://#', '', $domain);

        // Tomar nombres del pool por tenant — garantiza unicidad sin colisiones.
        $pool = &self::$namePool[$company->tenant_id];

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            if (empty($pool)) {
                break; // pool agotado para este tenant
            }
            [$fn, $ln] = array_shift($pool);
            $job = $jobs[array_rand($jobs)];
            $out[] = [
                'slug'           => Str::random(22),
                'first_name'     => $fn,
                'last_name'      => $ln,
                'name'           => "{$fn} {$ln}",
                'job_title'      => $job,
                'department'     => $i === 0 ? 'Dirección' : 'Operaciones',
                'primary_email'  => strtolower(Str::ascii("{$fn}.{$ln}")) . '@' . $domain,
                'primary_phone'  => '+51 9' . rand(10000000, 99999999),
                'lifecycle_stage'=> $company->lifecycle_stage === 'customer' ? 'customer' : 'lead',
                'rating'         => $i === 0 ? 'hot' : 'warm',
                'seniority_level'=> $seniorities[array_rand($seniorities)],
                'decision_role'  => $i === 0 ? 'economic_buyer' : $roles[array_rand($roles)],
                'is_decision_maker' => $i === 0,
                'email_opt_in'   => true,
                'sms_opt_in'     => true,
                'whatsapp_opt_in'=> true,
                'do_not_contact' => false,
                'relationship_strength' => $i === 0 ? 'strong' : 'warm',
                'preferred_channel' => 'email',
            ];
        }
        return $out;
    }

    protected function dealTemplates(Tenant $tenant, $companies, $stages, $leadSources): array
    {
        $stageProsp   = $stages[0]; // Prospección
        $stageQual    = $stages[1]; // Calificación
        $stageProp    = $stages[2]; // Propuesta
        $stageNeg     = $stages[3]; // Negociación
        $stageWon     = $stages[4]; // Ganada
        $stageLost    = $stages[5]; // Perdida

        $now = Carbon::now();

        // 12-15 deals mix de open/won/lost
        $templates = [
            ['name' => 'Licencia anual 50 usuarios',       'value' => 25000, 'stage' => $stageProp, 'status' => 'open', 'probability_pct' => 50, 'expected_close_date' => $now->copy()->addDays(15)->toDateString()],
            ['name' => 'Plan Enterprise renovación 2026',  'value' => 48000, 'stage' => $stageNeg,  'status' => 'open', 'probability_pct' => 75, 'expected_close_date' => $now->copy()->addDays(7)->toDateString()],
            ['name' => 'Implementación módulo Inventario', 'value' => 12000, 'stage' => $stageWon,  'status' => 'won',  'probability_pct' => 100, 'won_at' => $now->copy()->subDays(5)->toDateTimeString(), 'expected_close_date' => $now->copy()->subDays(5)->toDateString()],
            ['name' => 'Upgrade plan Pro → Enterprise',    'value' => 18000, 'stage' => $stageWon,  'status' => 'won',  'probability_pct' => 100, 'won_at' => $now->copy()->subDays(20)->toDateTimeString(), 'expected_close_date' => $now->copy()->subDays(20)->toDateString()],
            ['name' => 'Consultoría onboarding 3 meses',   'value' => 8500,  'stage' => $stageQual, 'status' => 'open', 'probability_pct' => 25, 'expected_close_date' => $now->copy()->addDays(30)->toDateString()],
            ['name' => 'POC piloto 10 usuarios',           'value' => 3500,  'stage' => $stageProsp,'status' => 'open', 'probability_pct' => 10, 'expected_close_date' => $now->copy()->addDays(45)->toDateString()],
            ['name' => 'Renovación contrato anual',        'value' => 32000, 'stage' => $stageNeg,  'status' => 'open', 'probability_pct' => 75, 'expected_close_date' => $now->copy()->addDays(10)->toDateString()],
            ['name' => 'Migración data legacy',            'value' => 15000, 'stage' => $stageLost, 'status' => 'lost', 'probability_pct' => 0,  'lost_at' => $now->copy()->subDays(15)->toDateTimeString(), 'lost_reason_note' => 'Cliente eligió competidor por precio (35% más barato)', 'expected_close_date' => $now->copy()->subDays(15)->toDateString()],
            ['name' => 'Módulo CRM customizado',           'value' => 22000, 'stage' => $stageProp, 'status' => 'open', 'probability_pct' => 50, 'expected_close_date' => $now->copy()->addDays(20)->toDateString()],
            ['name' => 'Integración API contable',         'value' => 6500,  'stage' => $stageQual, 'status' => 'open', 'probability_pct' => 25, 'expected_close_date' => $now->copy()->addDays(35)->toDateString()],
            ['name' => 'Plan starter mensual',             'value' => 1200,  'stage' => $stageWon,  'status' => 'won',  'probability_pct' => 100, 'won_at' => $now->copy()->subDays(2)->toDateTimeString(), 'expected_close_date' => $now->copy()->subDays(2)->toDateString()],
            ['name' => 'Training equipo ventas',           'value' => 4500,  'stage' => $stageLost, 'status' => 'lost', 'probability_pct' => 0,  'lost_at' => $now->copy()->subDays(8)->toDateTimeString(), 'lost_reason_note' => 'Postergaron a 2027 — recortaron presupuesto.', 'expected_close_date' => $now->copy()->subDays(10)->toDateString()],
            ['name' => 'Add-on reportería avanzada',       'value' => 5800,  'stage' => $stageProp, 'status' => 'open', 'probability_pct' => 50, 'expected_close_date' => $now->copy()->addDays(18)->toDateString()],
        ];

        $companyList = $companies->values()->all();
        $leadSrcList = $leadSources->values()->all();
        $defaultCurrency = $tenant->default_currency_code;

        $out = [];
        foreach ($templates as $i => $t) {
            $company = $companyList[$i % count($companyList)];
            $stage   = $t['stage'];
            $weight  = round((float)$t['value'] * ($t['probability_pct'] / 100), 2);

            $out[] = [
                'slug'                => Str::random(22),
                'name'                => $t['name'],
                'company_id'          => $company->id,
                'stage_id'            => $stage->id,
                'status'              => $t['status'],
                'value'               => $t['value'],
                'currency_code'       => $company->preferred_currency_code ?? $defaultCurrency,
                'probability_pct'     => $t['probability_pct'],
                'weighted_value'      => $weight,
                'expected_close_date' => $t['expected_close_date'] ?? null,
                'won_at'              => $t['won_at']  ?? null,
                'lost_at'             => $t['lost_at'] ?? null,
                'lost_reason_note'    => $t['lost_reason_note'] ?? null,
                'lead_source_id'      => $leadSrcList[array_rand($leadSrcList)]->id,
                'description'         => null,
            ];
        }
        return $out;
    }
}
