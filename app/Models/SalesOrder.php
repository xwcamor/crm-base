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

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'sales_orders';

    protected $fillable = [
        'slug', 'prefix', 'reference', 'external_reference',
        'quote_id', 'deal_id', 'company_id', 'contact_id',
        'status', 'warehouse_id',
        'order_date', 'expected_delivery_date', 'shipped_at', 'delivered_at', 'cancelled_at',
        'currency_code',
        'subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total',
        'payment_terms_days', 'payment_status',
        'shipping_address', 'billing_address',
        'notes', 'internal_notes',
        'owner_id', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'order_date'              => 'date',
        'expected_delivery_date'  => 'date',
        'shipped_at'              => 'datetime',
        'delivered_at'            => 'datetime',
        'cancelled_at'            => 'datetime',
        'subtotal'                => 'decimal:2',
        'discount_total'          => 'decimal:2',
        'tax_total'               => 'decimal:2',
        'shipping_cost'           => 'decimal:2',
        'grand_total'             => 'decimal:2',
        'shipping_address'        => 'array',
        'billing_address'         => 'array',
        'payment_terms_days'      => 'integer',
    ];

    public const STATUSES = ['pending', 'processing', 'partially_shipped', 'shipped', 'delivered', 'cancelled', 'closed'];
    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid', 'overdue'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                do { $slug = Str::random(22); } while (static::withTrashed()->where('slug', $slug)->exists());
                $model->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function items(): HasMany       { return $this->hasMany(SalesOrderItem::class)->orderBy('sort_order'); }
    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function contact(): BelongsTo   { return $this->belongsTo(Contact::class); }
    public function quote(): BelongsTo     { return $this->belongsTo(Quote::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function owner(): BelongsTo     { return $this->belongsTo(User::class, 'owner_id'); }

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
        return __('sales_orders.status_options.' . ($this->status ?? 'pending'));
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta reference (substring), status (single o multi), payment_status,
     * company_id (multiselect), order_date range, created_at range, id range,
     * only_favorites.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'sales_orders';

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

        $query->when($request->filled('payment_status'), function ($q) use ($request, $tbl) {
            $statuses = is_array($request->payment_status) ? $request->payment_status : [$request->payment_status];
            $statuses = array_filter($statuses, fn ($s) => $s !== '' && $s !== null);
            if (!empty($statuses)) $q->whereIn("{$tbl}.payment_status", $statuses);
        });

        $query->when($request->filled('company_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->company_id) ? $request->company_id : [$request->company_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.company_id", $ids);
        });

        $query->when($request->filled('warehouse_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->warehouse_id) ? $request->warehouse_id : [$request->warehouse_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.warehouse_id", $ids);
        });

        $query->when($request->filled('order_from'), fn ($q) => $q->where("{$tbl}.order_date", '>=', $request->order_from));
        $query->when($request->filled('order_to'),   fn ($q) => $q->where("{$tbl}.order_date", '<=', $request->order_to));
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
        $sortable  = ['id', 'reference', 'status', 'payment_status', 'order_date', 'expected_delivery_date', 'grand_total', 'created_at', 'updated_at'];
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
            ['key' => 'reference',      'label' => __('sales_orders.reference'),      'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'status',         'label' => __('sales_orders.status'),         'type' => 'enum',    'operators' => ['=', '!='], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('sales_orders.status_options.' . $s)], self::STATUSES)],
            ['key' => 'payment_status', 'label' => __('sales_orders.payment_status'), 'type' => 'enum',    'operators' => ['=', '!='], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('sales_orders.payment_status_options.' . $s)], self::PAYMENT_STATUSES)],
            ['key' => 'grand_total',    'label' => __('sales_orders.grand_total'),    'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'order_date',     'label' => __('sales_orders.order_date'),     'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',     'label' => __('global.created_at'),           'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
