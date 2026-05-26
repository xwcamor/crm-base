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

class Delivery extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'deliveries';

    protected $table = 'deliveries';

    protected $fillable = [
        'slug', 'reference', 'prefix', 'sales_order_id', 'warehouse_id',
        'status', 'expected_delivery_date', 'shipped_at', 'delivered_at', 'signed_by_name',
        'carrier', 'tracking_number', 'shipping_method', 'shipping_cost',
        'shipping_address', 'notes',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'shipped_at'             => 'datetime',
        'delivered_at'           => 'datetime',
        'shipping_cost'          => 'decimal:2',
        'shipping_address'       => 'array',
    ];

    public const STATUSES = ['pending', 'picking', 'packed', 'shipped', 'delivered', 'returned'];

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

    public function items(): HasMany        { return $this->hasMany(DeliveryItem::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }
    public function warehouse(): BelongsTo  { return $this->belongsTo(Warehouse::class); }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /** Texto traducido del status — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return __('deliveries.status_options.' . ($this->status ?? 'pending'));
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta reference (substring), status (multi), sales_order_id (multi),
     * warehouse_id (multi), carrier (substring), tracking (substring),
     * shipped_at range, created_at range, id range, only_favorites.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'deliveries';

        $query->when($request->filled('reference'), function ($q) use ($request, $isPgsql, $tbl) {
            $refs = is_array($request->reference) ? $request->reference : [$request->reference];
            $refs = array_filter($refs, fn ($r) => $r !== '');
            if (empty($refs)) return;
            $q->where(function ($qq) use ($refs, $isPgsql, $tbl) {
                foreach ($refs as $ref) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.reference)) LIKE unaccent(lower(?))", ['%' . $ref . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.reference", 'like', '%' . $ref . '%');
                    }
                }
            });
        });

        $query->when($request->filled('status'), function ($q) use ($request, $tbl) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $statuses = array_filter($statuses, fn ($s) => $s !== '' && $s !== null);
            if (!empty($statuses)) $q->whereIn("{$tbl}.status", $statuses);
        });

        $query->when($request->filled('sales_order_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->sales_order_id) ? $request->sales_order_id : [$request->sales_order_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.sales_order_id", $ids);
        });

        $query->when($request->filled('warehouse_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->warehouse_id) ? $request->warehouse_id : [$request->warehouse_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.warehouse_id", $ids);
        });

        $query->when($request->filled('carrier'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.carrier", 'like', '%' . $request->carrier . '%');
        });

        $query->when($request->filled('tracking_number'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.tracking_number", 'like', '%' . $request->tracking_number . '%');
        });

        $query->when($request->filled('shipped_from'), fn ($q) => $q->where("{$tbl}.shipped_at", '>=', $request->shipped_from . ' 00:00:00'));
        $query->when($request->filled('shipped_to'),   fn ($q) => $q->where("{$tbl}.shipped_at", '<=', $request->shipped_to . ' 23:59:59'));
        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
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

        $sort      = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');
        $sortable  = ['id', 'reference', 'status', 'shipped_at', 'delivered_at', 'expected_delivery_date', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados para el drawer "Filtros avanzados".
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'reference',       'label' => __('deliveries.reference'),       'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'status',          'label' => __('deliveries.status'),          'type' => 'enum',    'operators' => ['=', '!='], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('deliveries.status_options.' . $s)], self::STATUSES)],
            ['key' => 'carrier',         'label' => __('deliveries.carrier'),         'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'tracking_number', 'label' => __('deliveries.tracking_number'), 'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'shipping_cost',   'label' => __('deliveries.shipping_cost'),   'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'shipped_at',      'label' => __('deliveries.shipped_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',      'label' => __('global.created_at'),          'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
