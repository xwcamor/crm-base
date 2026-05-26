<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

use App\Traits\Auditable;
use App\Traits\HasFavorites;

/**
 * Message — anuncio/aviso/debate creado por super y dirigido a una audiencia.
 *
 * audience_type:
 *   - 'global' : todos los users humanos (excluye api/system users)
 *   - 'tenant' : todos los users del tenant cuyo id queda en audience_id
 *   - 'user'   : solo el user cuyo id queda en audience_id
 *
 * Al publicar (publish), el service materializa los recipients en la tabla
 * message_recipients. Esto evita recalcular la audiencia en cada lectura.
 *
 * Tier 1 parity: NO usa BelongsToTenant porque es super-only (no per-tenant).
 * Sí usa: SoftDeletes + Auditable + HasFavorites.
 */
class Message extends Model
{
    use HasFactory, SoftDeletes, Auditable, HasFavorites;

    protected string $auditModule = 'messages';

    protected $fillable = [
        'subject',
        'body',
        'created_by',
        'deleted_by',
        'deleted_description',
        'audience_type',
        'audience_id',
        'allow_replies',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'allow_replies' => 'boolean',
        'is_active'     => 'boolean',
        'published_at'  => 'datetime',
        'expires_at'    => 'datetime',
    ];

    // Constantes de audience_type para evitar strings sueltos por el codigo.
    public const AUDIENCE_GLOBAL = 'global';
    public const AUDIENCE_TENANT = 'tenant';
    public const AUDIENCE_USER   = 'user';

    public const AUDIENCES = [self::AUDIENCE_GLOBAL, self::AUDIENCE_TENANT, self::AUDIENCE_USER];

    protected static function booted(): void
    {
        static::creating(function ($message) {
            if (empty($message->slug)) {
                $attempts = 0;
                do {
                    $slug = Str::random(22);
                    $attempts++;
                } while ($attempts < 5 && Message::withTrashed()->where('slug', $slug)->exists());
                $message->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Override Auditable URL para que el audit log apunte al show de communication.
    public function getAuditUrl(): ?string
    {
        try {
            return route('communication.messages.show', $this->slug);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        // `withoutGlobalScopes` bypassea HideSuperScope: el creator de un
        // mensaje (siempre super) debe ser visible para el recipient (no-super).
        // Sin esto, el recipient verá el mensaje pero la relacion creator() devuelve
        // null porque el scope filtra al super creator.
        return $this->belongsTo(User::class, 'created_by')
            ->withTrashed()
            ->withoutGlobalScopes();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')
            ->withTrashed()
            ->withoutGlobalScopes();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MessageReply::class)->orderBy('created_at');
    }

    // ─── Helpers de estado ───────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * El mensaje esta "visible" en el inbox si: publicado, activo y no vencido.
     */
    public function isVisible(): bool
    {
        return $this->isPublished()
            && $this->is_active
            && !$this->isExpired();
    }

    // ─── Accessors para exports (CSV / Excel / PDF / Word) ───────────────────

    /** Texto traducido del estado is_active — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    /** Texto traducido del audience_type — consumido por exports. */
    public function getAudienceTypeTextAttribute(): string
    {
        return match ($this->audience_type) {
            self::AUDIENCE_GLOBAL => __('messages.audience_global'),
            self::AUDIENCE_TENANT => __('messages.audience_tenant'),
            self::AUDIENCE_USER   => __('messages.audience_user'),
            default               => (string) $this->audience_type,
        };
    }

    /** Estado calculado de publicacion para exports/listados. */
    public function getStatusTextAttribute(): string
    {
        if (!$this->isPublished()) return __('messages.status_draft');
        if ($this->isExpired())    return __('messages.status_expired');
        return __('messages.status_published');
    }

    // ─── Filtros (scopeFilter) ───────────────────────────────────────────────

    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'messages';

        $query->when($request->filled('subject'), function ($q) use ($request, $isPgsql, $tbl) {
            $subjects = is_array($request->subject) ? $request->subject : [$request->subject];
            $subjects = array_filter($subjects, fn ($s) => $s !== '');
            if (empty($subjects)) return;
            $q->where(function ($qq) use ($subjects, $isPgsql, $tbl) {
                foreach ($subjects as $subject) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.subject)) LIKE unaccent(lower(?))", ['%' . $subject . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.subject", 'like', '%' . $subject . '%');
                    }
                }
            });
        });

        $query->when($request->filled('audience_type'), fn ($q) => $q->where("{$tbl}.audience_type", $request->audience_type));

        $query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->has('allow_replies') && $request->allow_replies !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.allow_replies", filter_var($request->allow_replies, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->filled('published_from'), fn ($q) => $q->where("{$tbl}.published_at", '>=', $request->published_from . ' 00:00:00'));
        $query->when($request->filled('published_to'),   fn ($q) => $q->where("{$tbl}.published_at", '<=', $request->published_to . ' 23:59:59'));
        $query->when($request->filled('expires_from'),   fn ($q) => $q->where("{$tbl}.expires_at", '>=', $request->expires_from . ' 00:00:00'));
        $query->when($request->filled('expires_to'),     fn ($q) => $q->where("{$tbl}.expires_at", '<=', $request->expires_to . ' 23:59:59'));
        $query->when($request->filled('created_from'),   fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),     fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
        $query->when($request->filled('id_from'), fn ($q) => $q->where("{$tbl}.id", '>=', (int) $request->id_from));
        $query->when($request->filled('id_to'),   fn ($q) => $q->where("{$tbl}.id", '<=', (int) $request->id_to));

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
            'id', 'subject', 'audience_type',
            'published_at', 'expires_at',
            'is_active', 'created_at', 'updated_at',
        ];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema para el drawer de "Filtros avanzados". Mismo shape que Discount.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'subject',       'label' => __('messages.subject'),       'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'audience_type', 'label' => __('messages.audience_type'), 'type' => 'enum',    'operators' => ['=', '!='],
                'options' => collect(self::AUDIENCES)->map(fn ($t) => ['value' => $t, 'label' => __('messages.audience_' . $t)])->all()],
            ['key' => 'is_active',     'label' => __('messages.is_active'),     'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'allow_replies', 'label' => __('messages.allow_replies'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'published_at',  'label' => __('messages.published_at'),  'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'expires_at',    'label' => __('messages.expires_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',    'label' => __('global.created_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',    'label' => __('global.updated_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
