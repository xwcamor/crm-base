<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'payments';

    protected $fillable = [
        'slug', 'reference',
        'company_id', 'invoice_id', 'type', 'payment_method_id',
        'amount', 'currency_code', 'exchange_rate', 'amount_in_invoice_currency',
        'paid_at', 'reconciled_at',
        'external_transaction_id', 'bank_reference', 'notes',
        'status', 'is_active',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'paid_at'                     => 'datetime',
        'reconciled_at'               => 'datetime',
        'amount'                      => 'decimal:2',
        'exchange_rate'               => 'decimal:6',
        'amount_in_invoice_currency'  => 'decimal:2',
        'is_active'                   => 'boolean',
    ];

    public const TYPES    = ['invoice_payment', 'deposit', 'credit_memo', 'refund'];
    public const STATUSES = ['pending', 'completed', 'failed', 'refunded', 'disputed'];

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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

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
        return __('payments.status_options.' . ($this->status ?? 'pending'));
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta reference (multi/contains), status, type, method, amount range,
     * paid_at range, company_id, invoice_id, created_at range, is_active,
     * only_favorites, advanced_where, sort + direction.
     */
    public function scopeFilter($query, Request $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'payments';

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

        $query->when($request->filled('type'), function ($q) use ($request, $tbl) {
            $types = is_array($request->type) ? $request->type : [$request->type];
            $types = array_filter($types, fn ($t) => $t !== '' && $t !== null);
            if (!empty($types)) $q->whereIn("{$tbl}.type", $types);
        });

        $query->when($request->filled('payment_method_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->payment_method_id) ? $request->payment_method_id : [$request->payment_method_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.payment_method_id", $ids);
        });

        $query->when($request->filled('company_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->company_id) ? $request->company_id : [$request->company_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.company_id", $ids);
        });

        $query->when($request->filled('invoice_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->invoice_id) ? $request->invoice_id : [$request->invoice_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.invoice_id", $ids);
        });

        $query->when($request->filled('amount_from'), fn ($q) => $q->where("{$tbl}.amount", '>=', (float) $request->amount_from));
        $query->when($request->filled('amount_to'),   fn ($q) => $q->where("{$tbl}.amount", '<=', (float) $request->amount_to));
        $query->when($request->filled('paid_from'), fn ($q) => $q->where("{$tbl}.paid_at", '>=', $request->paid_from . ' 00:00:00'));
        $query->when($request->filled('paid_to'),   fn ($q) => $q->where("{$tbl}.paid_at", '<=', $request->paid_to . ' 23:59:59'));
        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));

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
        $sortable  = ['id', 'reference', 'status', 'type', 'amount', 'paid_at', 'created_at', 'updated_at'];
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
            ['key' => 'reference',         'label' => __('payments.reference'),      'type' => 'string', 'operators' => ['=', '!=', 'contains', 'in']],
            ['key' => 'status',            'label' => __('payments.status'),         'type' => 'enum',   'operators' => ['=', '!=', 'in'], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('payments.status_options.' . $s)], self::STATUSES)],
            ['key' => 'type',              'label' => __('payments.type'),           'type' => 'enum',   'operators' => ['=', '!=', 'in'], 'options' => array_map(fn ($t) => ['value' => $t, 'label' => __('payments.type_options.' . $t)], self::TYPES)],
            ['key' => 'payment_method_id', 'label' => __('payments.payment_method'), 'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'amount',            'label' => __('payments.amount'),         'type' => 'number', 'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'paid_at',           'label' => __('payments.paid_at'),        'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'company_id',        'label' => __('payments.company'),        'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'invoice_id',        'label' => __('payments.invoice'),        'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'created_at',        'label' => __('global.created_at'),       'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'is_active',         'label' => __('payments.is_active'),      'type' => 'boolean','operators' => ['=']],
        ];
    }
}
