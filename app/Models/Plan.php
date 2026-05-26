<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan — tier de pricing editable por super desde UI.
 *
 * Reemplaza la config hardcoded en config/features.php. Los límites y
 * features ahora viven en DB y se pueden cambiar sin redeploy.
 *
 * Helpers de Tenant (maxUsers, canUseFeature, etc.) leen de acá.
 */
class Plan extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'plans';

    protected $fillable = [
        'slug', 'name', 'tagline', 'icon', 'color', 'sort_order',
        'max_users', 'max_records_per_module', 'export_rate_limit',
        'support_level',
        'features',
        'price_monthly', 'price_yearly', 'currency',
        'is_active', 'is_public',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    /** Niveles de soporte validos para `support_level`. */
    public const SUPPORT_LEVELS = ['community', 'email', 'priority'];

    // Dependents: si hay tenants/subs apuntando, no force-delete sin migrar.
    public function dependents(): array
    {
        return [
            'tenants' => [
                'count' => fn () => $this->tenantsCount(),
                'label' => 'tenants',
                'block' => true,
            ],
            'subscriptions' => [
                'count' => fn () => Subscription::where('plan', $this->slug)->count(),
                'label' => 'subscriptions',
                'block' => false,
            ],
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    protected $casts = [
        'features'      => 'array',
        'is_active'     => 'boolean',
        'is_public'     => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly'  => 'decimal:2',
    ];

    /** Convertir -1 (ilimitado) a PHP_INT_MAX. */
    public function getMaxUsersAttribute($value): int
    {
        return $value < 0 ? PHP_INT_MAX : (int) $value;
    }

    public function getMaxRecordsPerModuleAttribute($value): int
    {
        return $value < 0 ? PHP_INT_MAX : (int) $value;
    }

    public function hasFeature(string $key): bool
    {
        $features = $this->features ?? [];
        return (bool) ($features[$key] ?? false);
    }

    // ─── Relations ────────────────────────────────────────────────────────
    //
    // `subscriptions.plan` es un STRING (slug), no FK — para mantener el plan
    // inmutable en histórico aunque el plan se renombre. No existe
    // `tenants.plan`: el plan de un tenant se deriva de su suscripción
    // vigente. Los counts ayudan al admin a decidir (ej. "no desactives free,
    // hay 12 tenants ahí").

    /**
     * Tenants "en este plan" = los que tienen una suscripción vigente para él.
     * `free` es especial: es la AUSENCIA de suscripción vigente (el piso).
     */
    public function tenants()
    {
        if ($this->slug === 'free') {
            return Tenant::query()->whereDoesntHave('subscriptions', fn ($q) => $q->current());
        }
        return Tenant::query()->whereHas('subscriptions', function ($q) {
            $q->current()->where('plan', $this->slug);
        });
    }

    public function activeSubscriptions()
    {
        return Subscription::query()->where('plan', $this->slug)->current();
    }

    public function tenantsCount(): int
    {
        return $this->tenants()->count();
    }

    public function activeSubscriptionsCount(): int
    {
        return Subscription::where('plan', $this->slug)->current()->count();
    }

    /**
     * Busca un plan por slug. Cache request-scoped para evitar N+1 cuando se
     * consulta el mismo plan múltiples veces (ej. middleware + helpers).
     */
    private static array $cache = [];

    public static function findBySlug(string $slug): ?Plan
    {
        if (array_key_exists($slug, self::$cache)) {
            return self::$cache[$slug];
        }
        $plan = static::where('slug', $slug)->where('is_active', true)->first();

        // Solo cacheamos hits. Cachear un miss (null) envenena la cache: si el
        // plan se crea despues via raw query (seeders, imports, tests) los
        // eventos Eloquent no disparan y el null queda pegado.
        if ($plan !== null) {
            self::$cache[$slug] = $plan;
        }
        return $plan;
    }

    /** Vacia la cache request-scoped. Util en tests entre casos. */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * Slugs de planes activos. Single source para validar `tenants.plan` y
     * `subscriptions.plan` desde FormRequests (reemplaza config('features.plans')
     * hardcoded).
     */
    public static function activeSlugs(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();
    }

    /**
     * Options shape para selects del frontend (Ant Design Select). Solo
     * planes activos y públicos (is_public=true) — los privados existen
     * pero no se ofrecen como opción al crear/editar workspaces.
     */
    public static function publicOptions(): array
    {
        return static::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'color', 'tagline'])
            ->map(fn ($p) => [
                'value'   => $p->slug,
                'label'   => strtoupper($p->name),
                'color'   => $p->color ?: 'default',
                'tagline' => $p->tagline ?: '',
            ])
            ->all();
    }

    /**
     * Payload completo para el modal "Comparar planes". Devuelve solo lo que
     * el frontend necesita renderizar dinámicamente (sin per-plan i18n keys):
     * el `tagline` viene de DB tal cual, las features son la JSON map directa.
     *
     * Cualquier plan nuevo que el super cree desde el módulo Plans
     * aparece automáticamente acá — sin tocar código ni traducciones.
     */
    public static function publicComparisonData(): array
    {
        return static::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'icon', 'color', 'max_users', 'max_records_per_module',
                   'export_rate_limit', 'support_level', 'price_monthly', 'price_yearly', 'currency', 'features'])
            ->map(fn ($p) => [
                'slug'                   => $p->slug,
                'name'                   => $p->name,
                'icon'                   => $p->icon,
                'color'                  => $p->color,
                'max_users'              => $p->getAttributes()['max_users'],
                'max_records_per_module' => $p->getAttributes()['max_records_per_module'],
                'export_rate_limit'      => $p->export_rate_limit,
                'support_level'          => $p->support_level ?: 'community',
                'price_monthly'          => (float) $p->price_monthly,
                'price_yearly'           => (float) $p->price_yearly,
                'currency'               => $p->currency,
                'features'               => $p->features ?? [],
            ])
            ->all();
    }

    protected static function booted(): void
    {
        // Invalidar cache cuando un plan cambia.
        static::saved(fn () => self::$cache = []);
        static::deleted(fn () => self::$cache = []);
    }

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    /** Texto traducido del support_level — consumido por exports. */
    public function getSupportTextAttribute(): string
    {
        $key = $this->support_level ?: 'community';
        return __('plans.support_' . $key);
    }

    /**
     * Filtros aplicables al listado. Mismo patron que Discount::scopeFilter.
     * Plans es super-only — sin gating por tenant.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'plans';

        $query->when($request->filled('name'), function ($q) use ($request, $isPgsql, $tbl) {
            $names = is_array($request->name) ? $request->name : [$request->name];
            $names = array_filter($names, fn ($n) => $n !== '');
            if (empty($names)) return;
            $q->where(function ($qq) use ($names, $isPgsql, $tbl) {
                foreach ($names as $name) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))", ['%' . $name . '%']);
                        $qq->orWhereRaw("unaccent(lower({$tbl}.slug)) LIKE unaccent(lower(?))", ['%' . $name . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.name", 'like', '%' . $name . '%');
                        $qq->orWhere("{$tbl}.slug", 'like', '%' . $name . '%');
                    }
                }
            });
        });

        $query->when($request->filled('slug'), fn ($q) => $q->where("{$tbl}.slug", 'like', '%' . $request->slug . '%'));

        $query->when($request->filled('support_level'), fn ($q) => $q->where("{$tbl}.support_level", $request->support_level));

        $query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });
        $query->when($request->has('is_public') && $request->is_public !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_public", filter_var($request->is_public, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to   . ' 23:59:59'));
        $query->when($request->filled('updated_from'), fn ($q) => $q->where("{$tbl}.updated_at", '>=', $request->updated_from . ' 00:00:00'));
        $query->when($request->filled('updated_to'),   fn ($q) => $q->where("{$tbl}.updated_at", '<=', $request->updated_to   . ' 23:59:59'));

        $advanced = $request->input('advanced_where');
        if (is_string($advanced)) {
            $advanced = json_decode($advanced, true) ?: null;
        }
        if (is_array($advanced) && !empty($advanced)) {
            \App\Services\Automations\Support\FilterApplier::apply(
                $query,
                ['where' => $advanced],
                static::filterSchema()
            );
        }

        $sort      = $request->get('sort', 'sort_order');
        $direction = $request->get('direction', 'asc');
        $sortable  = ['id', 'slug', 'name', 'sort_order', 'max_users', 'max_records_per_module',
                      'price_monthly', 'price_yearly', 'is_active', 'is_public',
                      'created_at', 'updated_at'];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados — declara que columnas el drawer "Filtros
     * avanzados" puede mostrar como condiciones. Misma shape que Discount.
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'slug',          'label' => __('plans.slug'),          'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'name',          'label' => __('plans.name'),          'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'support_level', 'label' => __('plans.support_level'), 'type' => 'enum',    'operators' => ['=', '!='],
                'options' => collect(self::SUPPORT_LEVELS)->map(fn ($t) => ['value' => $t, 'label' => __('plans.support_' . $t)])->all()],
            ['key' => 'max_users',     'label' => __('plans.max_users'),     'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'price_monthly', 'label' => __('plans.price_monthly'), 'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'is_active',     'label' => __('plans.is_active'),     'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'is_public',     'label' => __('plans.is_public'),     'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',    'label' => __('global.created_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',    'label' => __('global.updated_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
