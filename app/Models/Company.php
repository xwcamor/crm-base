<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Company — la entidad central del CRM. Representa una empresa con la que
 * el tenant tiene relación: cliente actual, prospecto, proveedor, partner.
 *
 * Multi-tenant: cada workspace ve solo sus companies (via BelongsToTenant).
 * Tax_id es unique por workspace via partial unique index.
 *
 * Lifecycle stages siguen el modelo HubSpot (subscriber → lead → MQL → SQL →
 * opportunity → customer → evangelist). Company_type permite distinguir
 * relación comercial (customer/supplier/both/prospect/partner).
 */
class Company extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected $table = 'companies';
    protected string $auditModule = 'companies';

    protected $fillable = [
        'slug', 'name', 'legal_name', 'description',
        'tax_id', 'tax_status', 'company_type', 'lifecycle_stage',
        'country_id', 'industry_id', 'owner_id', 'parent_company_id',
        'website', 'annual_revenue', 'employee_count', 'founded_year',
        'external_id', 'prefix', 'reference',

        // Monetario / pagos
        'preferred_currency_code', 'payment_terms_days', 'credit_limit',

        // Comunicación
        'preferred_language_id', 'billing_email',

        // Scoring
        'rating', 'score',

        // Social / branding
        'logo_url', 'linkedin_url', 'facebook_url', 'twitter_handle', 'instagram_url',

        // Priorización + post-venta
        'domain', 'is_vip', 'priority', 'customer_since', 'account_manager_id',

        // Pro fields (fiscal + health)
        'tax_exempt', 'tax_exempt_reason', 'legal_entity_type', 'bank_account_info',
        'discount_default_pct', 'default_payment_method_id',
        'account_status', 'health_score', 'churn_risk',
        'referrer_company_id',

        'is_active', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'is_vip'                => 'boolean',
        'tax_exempt'            => 'boolean',
        'annual_revenue'        => 'decimal:2',
        'credit_limit'          => 'decimal:2',
        'discount_default_pct'  => 'decimal:2',
        'employee_count'        => 'integer',
        'founded_year'          => 'integer',
        'payment_terms_days'    => 'integer',
        'score'                 => 'integer',
        'health_score'          => 'integer',
        'customer_since'        => 'date',
    ];

    /** Enum-style lists (frontend dropdowns + validation). */
    public const COMPANY_TYPES = ['prospect', 'customer', 'supplier', 'both', 'partner'];
    public const LIFECYCLE_STAGES = [
        'subscriber', 'lead', 'mql', 'sql',
        'opportunity', 'customer', 'evangelist', 'other',
    ];
    public const RATINGS = ['hot', 'warm', 'cold', 'none'];
    public const TAX_STATUSES = ['resident', 'non_resident', 'exempt'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    public const ACCOUNT_STATUSES = ['active', 'at_risk', 'churned', 'paused'];
    public const CHURN_RISKS = ['low', 'medium', 'high', 'critical'];
    public const LEGAL_ENTITY_TYPES = ['S.A.', 'S.A.S.', 'S.R.L.', 'S.A.C.', 'LLC', 'Corp', 'Inc.', 'Ltd.', 'GmbH', 'Sole Proprietorship', 'Other'];

protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                do {
                    $slug = Str::random(22);
                } while (static::withTrashed()->where('slug', $slug)->exists());
                $model->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    // ─── CRM Relations ────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function currency(): BelongsTo
    {
        // FK por code, no por id (las currencies tienen code como key natural).
        return $this->belongsTo(Currency::class, 'preferred_currency_code', 'code');
    }

    public function preferredLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'preferred_language_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    public function referrerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'referrer_company_id');
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PaymentMethod::class, 'default_payment_method_id');
    }

    public function children()
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }

    // Contacts: 1:N — un contact pertenece a 1 company, una company tiene N contactos.
    // (Modelo Contact se crea en el próximo scaffold).
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    // Deals (oportunidades): 1:N — usado por el Index para contar deals abiertos
    // (withCount) y por el Company Show tab Deals (tab cross-ref del PR #17).
    public function deals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deal::class);
    }

    // Quotes: 1:N — usado por el Company Show tab Cotizaciones.
    public function quotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Quote::class);
    }

    // Invoices: 1:N — usado por el Company Show tab Facturas.
    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Polymorphic helpers (addresses, emails, phones, notes, files, tags).
    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function emails()
    {
        return $this->morphMany(Email::class, 'emailable');
    }

    public function phones()
    {
        return $this->morphMany(Phone::class, 'phoneable');
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        // Columnas con prefijo `companies.*` para evitar ambiguedad cuando
        // otros scopes (ej. orderByFavoriteFirst) hacen JOIN.
        $tbl = 'companies';

        $query->when($request->filled('name'), function ($q) use ($request, $isPgsql, $tbl) {
            $names = is_array($request->name) ? $request->name : [$request->name];
            $names = array_filter($names, fn ($n) => $n !== '');
            if (empty($names)) return;
            $q->where(function ($qq) use ($names, $isPgsql, $tbl) {
                foreach ($names as $name) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))", ['%' . $name . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.name", 'like', '%' . $name . '%');
                    }
                }
            });
        });

$query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
        $query->when($request->filled('updated_from'), fn ($q) => $q->where("{$tbl}.updated_at", '>=', $request->updated_from . ' 00:00:00'));
        $query->when($request->filled('updated_to'),   fn ($q) => $q->where("{$tbl}.updated_at", '<=', $request->updated_to . ' 23:59:59'));
        $query->when($request->filled('id_from'), fn ($q) => $q->where("{$tbl}.id", '>=', (int) $request->id_from));
        $query->when($request->filled('id_to'),   fn ($q) => $q->where("{$tbl}.id", '<=', (int) $request->id_to));

        // Filtros avanzados (drawer): array de clausulas {field, op, value}
        // que el frontend manda como JSON en `advanced_where`. Cada clausula
        // se valida contra el schema declarado en filterSchema() y se aplica
        // con coercion tipada via FilterApplier (reutilizado de Automations).
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

        if ($request->filled('only_favorites') && filter_var($request->only_favorites, FILTER_VALIDATE_BOOLEAN)) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereExists(function ($q) use ($userId, $tbl) {
                    $q->select(\DB::raw(1))
                      ->from('user_favorites')
                      ->whereColumn('user_favorites.favoritable_id', "{$tbl}.id")
                      ->where('user_favorites.favoritable_type', static::class)
                      ->where('user_favorites.user_id', $userId);
                });
            }
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');
        $sortable = [
            'id', 'name',
            'is_active', 'created_at', 'updated_at',
        ];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados — declara qué columnas el drawer "Filtros
     * avanzados" puede mostrar como condiciones. Cada field expone:
     *   - key       : columna real de la BD (prefijada con `companies.`)
     *   - label     : texto i18n para el dropdown del field
     *   - type      : string | number | boolean | date | enum (el frontend
     *                 renderiza el control de "valor" según este type)
     *   - operators : subset de operadores que aplican
     *   - options   : (solo enum) opciones del dropdown
     *
     * Reusa el mismo shape que automations.DataSourceContract — el frontend
     * puede compartir el componente AdvancedFilterDrawer entre módulos.
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'name',       'label' => __('companies.name'),       'type' => 'string',  'operators' => ['=', '!=', 'contains']],
['key' => 'is_active',  'label' => __('companies.is_active'),  'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at', 'label' => __('global.created_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at', 'label' => __('global.updated_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
