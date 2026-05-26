<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Industry — taxonomía global de industrias (Software, Manufacturing, etc.).
 *
 * Sin tenant: catálogo compartido por todos los workspaces. Se permite
 * jerarquía via parent_id (Software > SaaS > B2B).
 *
 * Seed inicial: 40 industrias estándar B2B (HubSpot reference).
 */
class Industry extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'industries';

    protected $fillable = [
        'slug', 'name', 'parent_id', 'is_active',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Industry::class, 'parent_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
