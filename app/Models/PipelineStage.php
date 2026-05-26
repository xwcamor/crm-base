<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PipelineStage — columnas del kanban dentro de un pipeline.
 *
 * Ej: "Prospecting → Qualified → Proposal → Negotiation → Closed Won/Lost".
 * `probability_pct` alimenta el weighted forecast.
 */
class PipelineStage extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'pipeline_stages';

    protected $fillable = [
        'slug', 'pipeline_id', 'name', 'description',
        'color', 'sort_order', 'probability_pct',
        'is_won', 'is_lost', 'rot_days', 'is_active',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_won'           => 'boolean',
        'is_lost'          => 'boolean',
        'sort_order'       => 'integer',
        'probability_pct'  => 'integer',
        'rot_days'         => 'integer',
    ];

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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id');
    }
}
