<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use App\Traits\Auditable;
use App\Traits\HasFavorites;
use App\Traits\HasDependents;

class Locale extends Model
{
    use HasFactory, SoftDeletes, Auditable, HasFavorites, HasDependents;

    protected string $auditModule = 'locales';

    protected $fillable = [
        'code',
        'name',
        'language_id',
        'is_active',
        'created_by',
        'deleted_by',
        'deleted_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * users.locale_id Y countries.default_locale_id apuntan acá con FKs
     * NOT NULL → si los hay, no podemos eliminar el locale (ambos bloquean).
     */
    public function dependents(): array
    {
        return [
            'users' => [
                'model' => \App\Models\User::class,
                'fk'    => 'locale_id',
                'label' => 'users',
                'block' => true,
            ],
            'countries' => [
                'model' => \App\Models\Country::class,
                'fk'    => 'default_locale_id',
                'label' => 'countries',
                'block' => true,
            ],
        ];
    }

    protected static function booted()
    {
        static::creating(function ($locale) {
            $attempts = 0;
            do {
                $slug = Str::random(22);
                $attempts++;
            } while ($attempts < 5 && Locale::withTrashed()->where('slug', $slug)->exists());

            $locale->slug = $slug;
        });

        static::deleted(function ($locale) {
            if (!$locale->isForceDeleting()) return;
            \App\Models\UserFavorite::where('favoritable_type', static::class)
                ->where('favoritable_id', $locale->id)
                ->delete();
            \App\Models\UserRecentView::where('viewable_type', static::class)
                ->where('viewable_id', $locale->id)
                ->delete();
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    /**
     * Filtros soportados: name (LIKE), code (exacta o array), language_id (array),
     * is_active, created/updated_from/to, id_from/id_to, only_favorites, sort.
     */
    public function scopeFilter(Builder $query, Request|array $filters): Builder
    {
        if (is_array($filters)) {
            $filters = new Request($filters);
        }

        $tbl = 'locales';

        if ($filters->filled('name')) {
            $names = is_array($filters->name) ? $filters->name : [$filters->name];
            $names = array_filter(array_map('trim', $names), fn($n) => $n !== '');

            if (count($names) > 0) {
                $isPgsql = DB::getDriverName() === 'pgsql';
                $query->where(function ($q) use ($names, $isPgsql, $tbl) {
                    foreach ($names as $name) {
                        if ($isPgsql) {
                            $q->orWhereRaw(
                                "unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))",
                                ['%' . $name . '%']
                            );
                        } else {
                            $q->orWhere("{$tbl}.name", 'like', '%' . $name . '%');
                        }
                    }
                });
            }
        }

        if ($filters->filled('code')) {
            $codes = is_array($filters->code) ? $filters->code : [$filters->code];
            $codes = array_filter(array_map(fn($c) => strtolower(trim($c)), $codes), fn($c) => $c !== '');
            if (count($codes) > 0) {
                $query->whereIn(DB::raw("LOWER({$tbl}.code)"), $codes);
            }
        }

        if ($filters->filled('language_id')) {
            $ids = is_array($filters->language_id) ? $filters->language_id : [$filters->language_id];
            $ids = array_filter(array_map('intval', $ids));
            if (count($ids) > 0) {
                $query->whereIn("{$tbl}.language_id", $ids);
            }
        }

        if ($filters->filled('is_active')) {
            $query->where("{$tbl}.is_active", filter_var($filters->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($filters->filled('created_from')) {
            $query->where("{$tbl}.created_at", '>=', $filters->created_from . ' 00:00:00');
        }
        if ($filters->filled('created_to')) {
            $query->where("{$tbl}.created_at", '<=', $filters->created_to . ' 23:59:59');
        }
        if ($filters->filled('updated_from')) {
            $query->where("{$tbl}.updated_at", '>=', $filters->updated_from . ' 00:00:00');
        }
        if ($filters->filled('updated_to')) {
            $query->where("{$tbl}.updated_at", '<=', $filters->updated_to . ' 23:59:59');
        }
        if ($filters->filled('id_from')) {
            $query->where("{$tbl}.id", '>=', (int) $filters->id_from);
        }
        if ($filters->filled('id_to')) {
            $query->where("{$tbl}.id", '<=', (int) $filters->id_to);
        }

        if ($filters->filled('only_favorites') && filter_var($filters->only_favorites, FILTER_VALIDATE_BOOLEAN)) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereExists(function ($q) use ($userId, $tbl) {
                    $q->select(DB::raw(1))
                      ->from('user_favorites')
                      ->whereColumn('user_favorites.favoritable_id', "{$tbl}.id")
                      ->where('user_favorites.favoritable_type', static::class)
                      ->where('user_favorites.user_id', $userId);
                });
            }
        }

        $sort      = $filters->get('sort', 'id');
        $direction = $filters->get('direction', 'asc');
        if (in_array($sort, ['id', 'name', 'code', 'language_id', 'is_active', 'created_at', 'updated_at', 'deleted_at']) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }
}
