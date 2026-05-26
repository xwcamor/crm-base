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
 * PurchaseOrder — modulo de Ordenes de Compra a proveedores.
 *
 * Patron base per-tenant: SoftDeletes + Auditable + BelongsToTenant + HasFavorites.
 * Clon del patron de Customer (Tier 1) adaptado a modelo con items + status.
 */
class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'purchase_orders';

    protected $fillable = [
        'slug', 'prefix', 'reference', 'external_reference',
        'supplier_company_id', 'status', 'warehouse_id',
        'order_date', 'expected_delivery_date', 'submitted_at', 'confirmed_at', 'cancelled_at',
        'currency_code',
        'subtotal', 'tax_total', 'discount_total', 'shipping_cost', 'grand_total',
        'payment_terms_days', 'terms_md', 'delivery_type', 'notes',
        'owner_id', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'order_date'             => 'date',
        'expected_delivery_date' => 'date',
        'submitted_at'           => 'datetime',
        'confirmed_at'           => 'datetime',
        'cancelled_at'           => 'datetime',
        'subtotal'               => 'decimal:2',
        'tax_total'              => 'decimal:2',
        'discount_total'         => 'decimal:2',
        'shipping_cost'          => 'decimal:2',
        'grand_total'            => 'decimal:2',
        'payment_terms_days'     => 'integer',
    ];

    public const STATUSES = ['draft', 'submitted', 'confirmed', 'partially_received', 'received', 'closed', 'cancelled'];

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

    public function items(): HasMany       { return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order'); }
    public function supplier(): BelongsTo  { return $this->belongsTo(Company::class, 'supplier_company_id'); }
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
    public function getStatusTextAttribute(): string
    {
        return $this->status ? __('purchase_orders.status_options.' . $this->status) : '';
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta reference (substring), status (multiselect), supplier_company_id
     * (multiselect), warehouse_id (multiselect), order_date range, created_at
     * range, only_favorites, advanced_where (drawer).
     */
    public function scopeFilter($query, $request)
    {
        $tbl = 'purchase_orders';

        $query->when($request->filled('reference'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.reference", 'like', '%' . $request->reference . '%');
        });

        $query->when($request->filled('status'), function ($q) use ($request, $tbl) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $statuses = array_filter($statuses, fn ($s) => $s !== '');
            if (!empty($statuses)) $q->whereIn("{$tbl}.status", $statuses);
        });

        $query->when($request->filled('supplier_company_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->supplier_company_id) ? $request->supplier_company_id : [$request->supplier_company_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.supplier_company_id", $ids);
        });

        $query->when($request->filled('warehouse_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->warehouse_id) ? $request->warehouse_id : [$request->warehouse_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.warehouse_id", $ids);
        });

        $query->when($request->filled('order_date_from'), fn ($q) => $q->where("{$tbl}.order_date", '>=', $request->order_date_from));
        $query->when($request->filled('order_date_to'),   fn ($q) => $q->where("{$tbl}.order_date", '<=', $request->order_date_to));
        $query->when($request->filled('created_from'),    fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),      fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
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

        $sort      = $request->get('sort', 'order_date');
        $direction = $request->get('direction', 'desc');
        $sortable  = ['id', 'reference', 'status', 'order_date', 'expected_delivery_date', 'grand_total', 'created_at', 'updated_at'];
        if (in_array($sort, $sortable) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * Schema de filtros avanzados para el drawer AdvancedFilterDrawer.
     *
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        $statusOpts = collect(self::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('purchase_orders.status_options.' . $s)])
            ->all();

        return [
            ['key' => 'reference',    'label' => __('purchase_orders.reference'),    'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'status',       'label' => __('purchase_orders.status'),       'type' => 'enum',    'operators' => ['=', '!='], 'options' => $statusOpts],
            ['key' => 'order_date',   'label' => __('purchase_orders.order_date'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'grand_total',  'label' => __('purchase_orders.grand_total'),  'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=', '!=']],
            ['key' => 'created_at',   'label' => __('global.created_at'),            'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',   'label' => __('global.updated_at'),            'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
