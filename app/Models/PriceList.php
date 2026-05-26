<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PriceList — listas de precios per segmento de cliente.
 *
 * Patron base per-tenant: SoftDeletes + Auditable + BelongsToTenant + HasFavorites
 * (mismo set que Customer/Discount master template).
 *
 * El identificador unique por tenant es `name` (case+accent insensitive). No hay
 * campo `code` separado — el name es el handle visible y el slug es la url-key.
 */
class PriceList extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'price_lists';

    protected $fillable = [
        'slug', 'name', 'description', 'currency_code',
        'valid_from', 'valid_until', 'global_discount_pct',
        'is_default', 'is_active', 'priority',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'valid_from'          => 'datetime',
        'valid_until'         => 'datetime',
        'global_discount_pct' => 'decimal:2',
        'is_default'          => 'boolean',
        'is_active'           => 'boolean',
        'priority'            => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->slug)) {
                do { $slug = Str::random(22); } while (static::withTrashed()->where('slug', $slug)->exists());
                $m->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function items(): HasMany { return $this->hasMany(PriceListItem::class); }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'price_lists';

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

        $query->when($request->filled('currency_code'), fn ($q) => $q->where("{$tbl}.currency_code", $request->currency_code));

        $query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->has('is_default') && $request->is_default !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_default", filter_var($request->is_default, FILTER_VALIDATE_BOOLEAN));
        });

        // valid_from / valid_until ranges
        $query->when($request->filled('valid_from_from'), fn ($q) => $q->where("{$tbl}.valid_from", '>=', $request->valid_from_from . ' 00:00:00'));
        $query->when($request->filled('valid_from_to'),   fn ($q) => $q->where("{$tbl}.valid_from", '<=', $request->valid_from_to . ' 23:59:59'));
        $query->when($request->filled('valid_until_from'), fn ($q) => $q->where("{$tbl}.valid_until", '>=', $request->valid_until_from . ' 00:00:00'));
        $query->when($request->filled('valid_until_to'),   fn ($q) => $q->where("{$tbl}.valid_until", '<=', $request->valid_until_to . ' 23:59:59'));

        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
        $query->when($request->filled('updated_from'), fn ($q) => $q->where("{$tbl}.updated_at", '>=', $request->updated_from . ' 00:00:00'));
        $query->when($request->filled('updated_to'),   fn ($q) => $q->where("{$tbl}.updated_at", '<=', $request->updated_to . ' 23:59:59'));
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
            'id', 'name', 'currency_code', 'global_discount_pct', 'priority',
            'valid_from', 'valid_until',
            'is_active', 'is_default', 'created_at', 'updated_at',
        ];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados — declara que columnas el drawer "Filtros
     * avanzados" puede mostrar como condiciones.
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'name',                'label' => __('price_lists.name'),                'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'currency_code',       'label' => __('price_lists.currency'),            'type' => 'string',  'operators' => ['=', '!=']],
            ['key' => 'global_discount_pct', 'label' => __('price_lists.global_discount_pct'), 'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'priority',            'label' => __('price_lists.priority'),            'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'is_active',           'label' => __('price_lists.is_active'),           'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'is_default',          'label' => __('price_lists.is_default'),          'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'valid_from',          'label' => __('price_lists.valid_from'),          'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'valid_until',         'label' => __('price_lists.valid_until'),         'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',          'label' => __('global.created_at'),               'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',          'label' => __('global.updated_at'),               'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
