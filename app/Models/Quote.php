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

class Quote extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'quotes';

    protected $fillable = [
        'slug', 'prefix', 'reference', 'external_reference',
        'deal_id', 'company_id', 'contact_id',
        'status', 'is_active',
        'issue_date', 'valid_until',
        'sent_at', 'accepted_at', 'rejected_at', 'rejected_reason',
        'signed_by_name', 'signed_by_email', 'signed_by_ip',
        'currency_code', 'exchange_rate',
        'subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total',
        'discount_id',
        'terms_md', 'notes', 'internal_notes',
        'owner_id',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'issue_date'     => 'date',
        'valid_until'    => 'date',
        'sent_at'        => 'datetime',
        'accepted_at'    => 'datetime',
        'rejected_at'    => 'datetime',
        'exchange_rate'  => 'decimal:6',
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total'      => 'decimal:2',
        'shipping_cost'  => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'revised'];

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
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Texto traducido del status — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return __('quotes.status_options.' . ($this->status ?? 'draft'));
    }

    /**
     * scopeFilter — aplica los filtros del request al query.
     *
     * Soporta reference (multi/contains), status (single/array), company_id,
     * contact_id, deal_id, issue_date range, created_at range, only_favorites,
     * is_active, advanced_where (JSON), sort + direction.
     */
    public function scopeFilter($query, Request $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'quotes';

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

        $query->when($request->filled('deal_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->deal_id) ? $request->deal_id : [$request->deal_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.deal_id", $ids);
        });

        $query->when($request->filled('issue_from'), fn ($q) => $q->where("{$tbl}.issue_date", '>=', $request->issue_from));
        $query->when($request->filled('issue_to'),   fn ($q) => $q->where("{$tbl}.issue_date", '<=', $request->issue_to));
        $query->when($request->filled('valid_from'), fn ($q) => $q->where("{$tbl}.valid_until", '>=', $request->valid_from));
        $query->when($request->filled('valid_to'),   fn ($q) => $q->where("{$tbl}.valid_until", '<=', $request->valid_to));
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
        $sortable  = ['id', 'reference', 'status', 'issue_date', 'valid_until', 'grand_total', 'created_at', 'updated_at'];
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
            ['key' => 'reference',    'label' => __('quotes.reference'),   'type' => 'string', 'operators' => ['=', '!=', 'contains', 'in']],
            ['key' => 'status',       'label' => __('quotes.status'),      'type' => 'enum',   'operators' => ['=', '!=', 'in'], 'options' => array_map(fn ($s) => ['value' => $s, 'label' => __('quotes.status_options.' . $s)], self::STATUSES)],
            ['key' => 'company_id',   'label' => __('quotes.company'),     'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'contact_id',   'label' => __('quotes.contact'),     'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'deal_id',      'label' => __('quotes.deal'),        'type' => 'number', 'operators' => ['=', '!=', 'in']],
            ['key' => 'issue_date',   'label' => __('quotes.issue_date'),  'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'valid_until',  'label' => __('quotes.valid_until'), 'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'grand_total',  'label' => __('quotes.grand_total'), 'type' => 'number', 'operators' => ['>', '<', '>=', '<=', '=']],
            ['key' => 'created_at',   'label' => __('global.created_at'),  'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'is_active',    'label' => __('quotes.is_active'),   'type' => 'boolean','operators' => ['=']],
        ];
    }
}
