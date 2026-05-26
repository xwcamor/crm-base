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
 * PaymentMethod — metodos de pago per-tenant.
 *
 * Tier 1 parity con Customer/Discount/ProductCategory master template:
 * SoftDeletes + Auditable + BelongsToTenant + HasFavorites.
 *
 * Ej: Transferencia, Cash, Stripe, MercadoPago, Cheque.
 */
class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'payment_methods';

    protected $table = 'payment_methods';

    protected $fillable = [
        'slug', 'name', 'code', 'description',
        'integration_provider', 'requires_reference',
        'is_active', 'sort_order',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'requires_reference' => 'boolean',
        'sort_order'         => 'integer',
    ];

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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }

    /** Texto traducido del estado — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'payment_methods';

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

        $query->when($request->filled('code'), fn ($q) => $q->where("{$tbl}.code", 'like', '%' . $request->code . '%'));
        $query->when($request->filled('integration_provider'), fn ($q) => $q->where("{$tbl}.integration_provider", $request->integration_provider));

        $query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->has('requires_reference') && $request->requires_reference !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.requires_reference", filter_var($request->requires_reference, FILTER_VALIDATE_BOOLEAN));
        });

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
            'id', 'name', 'code', 'sort_order', 'is_active', 'created_at', 'updated_at',
        ];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados — declara columnas que el drawer "Filtros
     * avanzados" puede mostrar como condiciones.
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'name',                 'label' => __('payment_methods.name'),                 'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'code',                 'label' => __('payment_methods.code'),                 'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'integration_provider', 'label' => __('payment_methods.integration_provider'), 'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'sort_order',           'label' => __('payment_methods.sort_order'),           'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'is_active',            'label' => __('payment_methods.is_active'),            'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'requires_reference',   'label' => __('payment_methods.requires_reference'),   'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',           'label' => __('global.created_at'),                    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',           'label' => __('global.updated_at'),                    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
