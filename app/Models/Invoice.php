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
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'invoices';

    protected $fillable = [
        'slug', 'number', 'prefix', 'reference', 'external_reference', 'document_type',
        'sales_order_id', 'subscription_id',
        'company_id', 'contact_id',
        'status', 'is_active',
        'issue_date', 'due_date', 'sent_at', 'paid_at', 'cancelled_at', 'cancellation_reason',
        'currency_code', 'exchange_rate',
        'subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total',
        'amount_paid', 'balance_due',
        'billing_address', 'billing_tax_id', 'billing_legal_name',
        'notes', 'terms_md', 'internal_notes',
        'owner_id',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'due_date'        => 'date',
        'sent_at'         => 'datetime',
        'paid_at'         => 'datetime',
        'cancelled_at'    => 'datetime',
        'exchange_rate'   => 'decimal:6',
        'subtotal'        => 'decimal:2',
        'discount_total'  => 'decimal:2',
        'tax_total'       => 'decimal:2',
        'shipping_cost'   => 'decimal:2',
        'grand_total'     => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'balance_due'     => 'decimal:2',
        'billing_address' => 'array',
        'is_active'       => 'boolean',
    ];

    public const STATUSES = ['draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled', 'refunded'];

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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /** SalesOrder origen — solo si la factura nace de un SO (no de un quote directo). */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SalesOrder::class, 'sales_order_id');
    }

    /** Texto traducido del status — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return __('invoices.status_options.' . ($this->status ?? 'draft'));
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta number + reference (multi/contains), status, company_id,
     * issue/due/created date ranges, only_overdue, only_favorites, is_active,
     * advanced_where, sort + direction.
     */
    public function scopeFilter($query, Request $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'invoices';

        $query->when($request->filled('number'), function ($q) use ($request, $isPgsql, $tbl) {
            $nums = is_array($request->number) ? $request->number : [$request->number];
            $nums = array_filter($nums, fn ($n) => $n !== '');
            if (empty($nums)) return;
            $q->where(function ($qq) use ($nums, $isPgsql, $tbl) {
                foreach ($nums as $n) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.number)) LIKE unaccent(lower(?))", ['%' . $n . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.number", 'like', '%' . $n . '%');
                    }
                }
            });
        });

        $query->when($request->filled('reference'), function ($q) use ($request, $isPgsql, $tbl) {
            $refs = is_array($request->reference) ? $request->reference : [$request->reference];
            $refs = array_filter($refs, fn ($r) => $r !== '');
            if (empty($refs)) return;
            $q->where(function ($qq) use ($refs, $isPgsql, $tbl) {
                foreach ($refs as $r) {
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.reference)) LIKE unaccent(lower(?))", ['%' . $r . '%']);
                    } else {
                        $qq->orWhere("{$tbl}.reference", 'like', '%' . $r . '%');
                    }
                }
            });
        });

        $query->when($request->filled('status'), function ($q) use ($request, $tbl) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $statuses = array_filter($statuses, fn ($s) => $s !== '' && $s !== null);
            if (!empty($statuses)) $q->whereIn("{$tbl}.status", $statuses);
        });

        $query->when($request->filled('company_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->company_id) ? $request->company_id : [$request->company_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.company_id", $ids);
        });

        $query->when($request->filled('contact_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->contact_id) ? $request->contact_id : [$request->contact_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.contact_id", $ids);
        });

        $query->when($request->filled('issue_from'), fn ($q) => $q->where("{$tbl}.issue_date", '>=', $request->issue_from));
        $query->when($request->filled('issue_to'),   fn ($q) => $q->where("{$tbl}.issue_date", '<=', $request->issue_to));
        $query->when($request->filled('due_from'),   fn ($q) => $q->where("{$tbl}.due_date", '>=', $request->due_from));
        $query->when($request->filled('due_to'),     fn ($q) => $q->where("{$tbl}.due_date", '<=', $request->due_to));
        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));

        if ($request->filled('only_overdue') && filter_var($request->only_overdue, FILTER_VALIDATE_BOOLEAN)) {
            $query->where("{$tbl}.balance_due", '>', 0)
                  ->where("{$tbl}.due_date", '<', now()->toDateString())
                  ->whereNotIn("{$tbl}.status", ['paid', 'cancelled', 'refunded']);
        }

        if ($request->filled('is_active')) {
            $query->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

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
        $sortable  = ['id', 'number', 'reference', 'status', 'issue_date', 'due_date', 'grand_total', 'amount_paid', 'balance_due', 'created_at', 'updated_at'];
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
            ['key' => 'number',       'label' => __('invoices.number'),       'type' => 'string',  'operators' => ['=', '!=', 'contains', 'in']],
            ['key' => 'reference',    'label' => __('invoices.reference'),    'type' => 'string',  'operators' => ['=', '!=', 'contains', 'in']],
            ['key' => 'status',       'label' => __('invoices.status'),       'type' => 'enum',    'operators' => ['=', '!=', 'in'], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('invoices.status_options.' . $s)], self::STATUSES)],
            ['key' => 'company_id',   'label' => __('invoices.company'),      'type' => 'number',  'operators' => ['=', '!=', 'in']],
            ['key' => 'issue_date',   'label' => __('invoices.issue_date'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'due_date',     'label' => __('invoices.due_date'),     'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'grand_total',  'label' => __('invoices.grand_total'),  'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'amount_paid',  'label' => __('invoices.amount_paid'),  'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'balance_due',  'label' => __('invoices.balance_due'),  'type' => 'number',  'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'only_overdue', 'label' => __('invoices.only_overdue'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',   'label' => __('global.created_at'),     'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'is_active',    'label' => __('invoices.is_active'),    'type' => 'boolean', 'operators' => ['=']],
        ];
    }
}
