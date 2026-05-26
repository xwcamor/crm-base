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
 * Contact — persona dentro del CRM, asociada típicamente a una Company.
 *
 * Diferencia con Company: Company = empresa (entidad legal), Contact = persona
 * (decisor, influencer, técnico, etc.). Un Contact puede no tener Company
 * (B2C / freelance) o tener una (B2B típico).
 */
class Contact extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'contacts';

    protected $fillable = [
        'slug', 'name', 'description',

        // Nombres + identidad profesional
        'first_name', 'last_name', 'middle_name', 'salutation',
        'job_title', 'department',

        // Contacto primario
        'primary_email', 'primary_phone', 'mobile_phone',

        // Relación CRM
        'company_id', 'reports_to_contact_id', 'is_primary_for_company',

        // Clasificación
        'lifecycle_stage', 'lead_source', 'rating', 'score',

        // Asignación
        'owner_id',

        // Preferencias
        'preferred_language_id', 'timezone',

        // Compliance
        'email_opt_in', 'sms_opt_in', 'whatsapp_opt_in', 'gdpr_consent_at', 'do_not_contact',

        // Personal
        'date_of_birth', 'gender',

        // Social
        'linkedin_url', 'twitter_handle', 'photo_url',

        // Sync
        'external_id',

        // Sales qualification
        'nickname', 'seniority_level', 'decision_role', 'is_decision_maker', 'preferred_channel',

        // Assistant
        'assistant_name', 'assistant_email', 'assistant_phone',

        // Marketing compliance + engagement
        'marketing_opt_in_at', 'marketing_opt_in_source', 'unsubscribed_at', 'unsubscribed_reason',
        'last_engagement_at', 'relationship_strength',

        'is_active', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'is_primary_for_company' => 'boolean',
        'is_decision_maker'      => 'boolean',
        'email_opt_in'           => 'boolean',
        'sms_opt_in'             => 'boolean',
        'whatsapp_opt_in'        => 'boolean',
        'do_not_contact'         => 'boolean',
        'score'                  => 'integer',
        'date_of_birth'          => 'date',
        'gdpr_consent_at'        => 'datetime',
        'marketing_opt_in_at'    => 'datetime',
        'unsubscribed_at'        => 'datetime',
        'last_engagement_at'     => 'datetime',
    ];

    public const LIFECYCLE_STAGES = ['subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist', 'other'];
    public const RATINGS = ['hot', 'warm', 'cold', 'none'];
    public const SALUTATIONS = ['Sr.', 'Sra.', 'Srta.', 'Dr.', 'Dra.', 'Ing.', 'Lic.'];
    public const GENDERS = ['male', 'female', 'other', 'prefer_not_to_say'];
    public const SENIORITY_LEVELS = ['intern', 'junior', 'mid', 'senior', 'manager', 'director', 'vp', 'c_level', 'owner'];
    public const DECISION_ROLES = ['economic_buyer', 'champion', 'influencer', 'technical', 'blocker', 'end_user', 'gatekeeper'];
    public const PREFERRED_CHANNELS = ['email', 'phone', 'whatsapp', 'sms', 'linkedin'];
    public const RELATIONSHIP_STRENGTHS = ['cold', 'warm', 'strong', 'champion'];

    /** Full name computado del first_name + last_name (fallback a `name`). */
    public function getFullNameAttribute(): string
    {
        $fn = trim($this->first_name ?? '');
        $ln = trim($this->last_name ?? '');
        $full = trim($fn . ' ' . $ln);
        return $full !== '' ? $full : (string) ($this->name ?? '');
    }

    // ─── Relations ──────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'reports_to_contact_id');
    }

    public function preferredLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'preferred_language_id');
    }

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

    /** Tags polimorficos — usados por Contact Show + filtros. */
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
        // Columnas con prefijo `contacts.*` para evitar ambiguedad cuando
        // otros scopes (ej. orderByFavoriteFirst) hacen JOIN.
        $tbl = 'contacts';

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
     *   - key       : columna real de la BD (prefijada con `contacts.`)
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
            ['key' => 'name',       'label' => __('contacts.name'),       'type' => 'string',  'operators' => ['=', '!=', 'contains']],
['key' => 'is_active',  'label' => __('contacts.is_active'),  'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at', 'label' => __('global.created_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at', 'label' => __('global.updated_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
