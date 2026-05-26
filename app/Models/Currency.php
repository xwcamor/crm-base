<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Currency — catálogo global de monedas ISO 4217.
 *
 * `code` es la clave natural (3 chars: USD, EUR, PEN). Otras tablas
 * referencian a currencies via `code` (FK lógico), no via id.
 *
 * Sin BelongsToTenant porque es catálogo compartido cross-tenant.
 */
class Currency extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'currencies';

    protected $fillable = [
        'slug', 'code', 'name', 'symbol', 'decimal_places', 'is_active',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'decimal_places' => 'integer',
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

    /** Formatea un monto con el símbolo + decimal_places de esta moneda. */
    public function format(float|int|string $amount): string
    {
        $decimals = $this->decimal_places ?? 2;
        return $this->symbol . ' ' . number_format((float) $amount, $decimals, '.', ',');
    }
}
