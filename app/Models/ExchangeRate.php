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
 * ExchangeRate — historial de tasas de cambio FX entre dos monedas.
 *
 * Append-only en uso: cada actualizacion (manual o automatica) inserta una
 * fila nueva con `valid_at` mas reciente. Para "tasa actual" se hace lookup
 * por (base_code, quote_code) ordenado desc por valid_at.
 *
 * Tier 1 parity con Customer/Discount: SoftDeletes + Auditable +
 * BelongsToTenant + HasFavorites. La unique key compuesta vive en la BD:
 * (tenant_id, base_code, quote_code, valid_at). El identificador display
 * NO es `name` (no existe) — es la triple "USD/PEN @ 2026-05-19", que se
 * usa para confirmar force-delete y para mostrar al usuario.
 */
class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    protected string $auditModule = 'exchange_rates';

    protected $fillable = [
        'slug', 'base_code', 'quote_code', 'rate', 'valid_at', 'source',
        'is_active', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'rate'      => 'decimal:6',
        'valid_at'  => 'datetime',
        'is_active' => 'boolean',
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

    /** Texto traducido del estado — consumido por exports. */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    /**
     * Identificador display sintetico — usado en force-delete confirmation,
     * exports y drawer detail. Formato: "USD/PEN @ 2026-05-19".
     */
    public function getDisplayNameAttribute(): string
    {
        $valid = $this->valid_at?->format('Y-m-d') ?? '—';
        return ($this->base_code ?? '???') . '/' . ($this->quote_code ?? '???') . ' @ ' . $valid;
    }

    /**
     * Lookup de la tasa mas reciente vigente para un par. Mantenido como
     * helper retrocompatible para callsites previos.
     */
    public static function latest_rate(string $base, string $quote): ?float
    {
        $row = static::where('base_code', $base)
            ->where('quote_code', $quote)
            ->where('is_active', true)
            ->orderBy('valid_at', 'desc')
            ->first();
        return $row ? (float) $row->rate : null;
    }

    public function scopeFilter($query, $request)
    {
        $tbl = 'exchange_rates';

        $query->when($request->filled('base_code'), function ($q) use ($request, $tbl) {
            $codes = is_array($request->base_code) ? $request->base_code : [$request->base_code];
            $codes = array_filter($codes, fn ($c) => $c !== '');
            if (!empty($codes)) $q->whereIn("{$tbl}.base_code", $codes);
        });

        $query->when($request->filled('quote_code'), function ($q) use ($request, $tbl) {
            $codes = is_array($request->quote_code) ? $request->quote_code : [$request->quote_code];
            $codes = array_filter($codes, fn ($c) => $c !== '');
            if (!empty($codes)) $q->whereIn("{$tbl}.quote_code", $codes);
        });

        $query->when($request->filled('source'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.source", 'like', '%' . $request->source . '%');
        });

        $query->when($request->has('is_active') && $request->is_active !== '', function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->filled('valid_from'), fn ($q) => $q->where("{$tbl}.valid_at", '>=', $request->valid_from . ' 00:00:00'));
        $query->when($request->filled('valid_to'),   fn ($q) => $q->where("{$tbl}.valid_at", '<=', $request->valid_to . ' 23:59:59'));

        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));

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

        $sort = $request->get('sort', 'valid_at');
        $direction = $request->get('direction', 'desc');
        $sortable = [
            'id', 'base_code', 'quote_code', 'rate', 'valid_at', 'source',
            'is_active', 'created_at', 'updated_at',
        ];
        if (in_array($sort, $sortable, true) && in_array($direction, ['asc', 'desc'], true)) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /** Schema para filtros avanzados drawer. */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'base_code',   'label' => __('exchange_rates.base_code'),   'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'quote_code',  'label' => __('exchange_rates.quote_code'),  'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'rate',        'label' => __('exchange_rates.rate'),        'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'source',      'label' => __('exchange_rates.source'),     'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'is_active',   'label' => __('exchange_rates.is_active'),   'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'valid_at',    'label' => __('exchange_rates.valid_at'),    'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',  'label' => __('global.created_at'),          'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
