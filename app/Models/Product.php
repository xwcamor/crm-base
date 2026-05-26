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
 * Product — master template del scaffold `php artisan make:module`.
 *
 * Patron base per-tenant: SoftDeletes + Auditable + BelongsToTenant + HasFavorites.
 * Las columnas custom del dominio se agregan a este modelo y a la migration.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'products';

    protected $fillable = [
        'slug', 'sku', 'barcode', 'name', 'description', 'long_description',
        'category_id', 'type', 'brand',
        'cost', 'final_cost', 'list_price', 'currency_code',
        'tax_class_id',
        'track_inventory', 'low_stock_threshold',
        'billing_cycle', 'billing_period',
        'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'image_url', 'external_id',
        'is_active', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'track_inventory'     => 'boolean',
        'cost'                => 'decimal:4',
        'final_cost'          => 'decimal:4',
        'list_price'          => 'decimal:4',
        'weight_kg'           => 'decimal:4',
        'length_cm'           => 'decimal:2',
        'width_cm'            => 'decimal:2',
        'height_cm'           => 'decimal:2',
        'low_stock_threshold' => 'integer',
        'billing_period'      => 'integer',
    ];

    public const TYPES = ['good', 'service', 'subscription', 'bundle'];
    public const BILLING_CYCLES = ['monthly', 'quarterly', 'yearly'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
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

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        // Columnas con prefijo `products.*` para evitar ambiguedad cuando
        // otros scopes (ej. orderByFavoriteFirst) hacen JOIN.
        $tbl = 'products';

        $query->when($request->filled('name'), function ($q) use ($request, $isPgsql, $tbl) {
            $names = is_array($request->name) ? $request->name : [$request->name];
            $names = array_filter($names, fn ($n) => $n !== '');
            if (empty($names)) return;
            $q->where(function ($qq) use ($names, $isPgsql, $tbl) {
                foreach ($names as $name) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))", ['%' . $name . '%']);
                        $qq->orWhereRaw("unaccent(lower({$tbl}.sku)) LIKE unaccent(lower(?))",  ['%' . $name . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.name", 'like', '%' . $name . '%');
                        $qq->orWhere("{$tbl}.sku",  'like', '%' . $name . '%');
                    }
                }
            });
        });

        $query->when($request->filled('sku'), fn ($q) => $q->where("{$tbl}.sku", 'like', '%' . $request->sku . '%'));
        $query->when($request->filled('type'), fn ($q) => $q->where("{$tbl}.type", $request->type));
        $query->when($request->filled('category_id'), fn ($q) => $q->where("{$tbl}.category_id", (int) $request->category_id));
        $query->when($request->filled('brand'), fn ($q) => $q->where("{$tbl}.brand", 'like', '%' . $request->brand . '%'));
        $query->when($request->filled('currency_code'), fn ($q) => $q->where("{$tbl}.currency_code", $request->currency_code));
        $query->when($request->filled('price_from'), fn ($q) => $q->where("{$tbl}.list_price", '>=', (float) $request->price_from));
        $query->when($request->filled('price_to'),   fn ($q) => $q->where("{$tbl}.list_price", '<=', (float) $request->price_to));

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
            'id', 'name', 'sku', 'type', 'brand', 'list_price', 'cost', 'currency_code',
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
     *   - key       : columna real de la BD (prefijada con `products.`)
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
            ['key' => 'name',          'label' => __('products.name'),         'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'sku',           'label' => __('products.sku'),          'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'barcode',       'label' => __('products.barcode'),      'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'brand',         'label' => __('products.brand'),        'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'type',          'label' => __('products.type'),         'type' => 'enum',    'operators' => ['=', '!='],
                'options' => collect(self::TYPES)->map(fn ($t) => ['value' => $t, 'label' => __('products.type_options.' . $t)])->all()],
            ['key' => 'category_id',   'label' => __('products.category'),     'type' => 'number',  'operators' => ['=', '!=']],
            ['key' => 'list_price',    'label' => __('products.list_price'),   'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'cost',          'label' => __('products.cost'),         'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'currency_code', 'label' => __('products.currency'),     'type' => 'string',  'operators' => ['=', '!=']],
            ['key' => 'track_inventory','label' => __('products.track_inventory'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'is_active',     'label' => __('products.is_active'),    'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',    'label' => __('global.created_at'),     'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',    'label' => __('global.updated_at'),     'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
