<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Activity — registro polimorfico de interaccion (note/call/email/meeting/task).
 *
 * Cada activity se asocia a un parent via morphTo: un Deal, Company o Contact.
 * En el Show de la entidad parent se ve el timeline filtrado por esa entidad.
 *
 * NO se filtra por is_active — el estado es pending (completed_at IS NULL) o
 * done (completed_at IS NOT NULL). Para "ocultar" una activity, se soft-deletea.
 */
class Activity extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'activities';

    public const TYPES = ['note', 'call', 'email', 'meeting', 'task'];

    public const CALL_OUTCOMES = ['answered', 'voicemail', 'no_answer', 'rejected'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const ALLOWED_PARENT_TYPES = [
        \App\Models\Deal::class,
        \App\Models\Company::class,
        \App\Models\Contact::class,
    ];

    protected $fillable = [
        'slug',
        'type', 'subject', 'body',
        'due_at', 'completed_at',
        'outcome', 'duration_min', 'location', 'priority',
        'attachment_path', 'attachment_name',
        'related_quote_id',
        'activitable_type', 'activitable_id',
        'actor_user_id', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
        'duration_min' => 'integer',
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

    // ── Relaciones ──
    public function activitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    /**
     * Quote (cotizacion/propuesta) opcionalmente linkeada a esta activity.
     * Si una activity tipo email/meeting referencia un quote, el frontend
     * muestra el link directo + auto-pre-fillea el subject desde el quote.
     */
    public function relatedQuote(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Quote::class, 'related_quote_id');
    }

    // ── Estado ──
    public function isPending(): bool
    {
        return $this->completed_at === null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->isPending()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function markComplete(): void
    {
        $this->completed_at = now();
        $this->save();
    }

    // ── Scopes ──
    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForActor($query, int $userId)
    {
        return $query->where('actor_user_id', $userId);
    }

    /**
     * Filtra por entidad parent. Si se pasa Deal $d, devuelve sus activities.
     */
    public function scopeForActivitable($query, Model $entity)
    {
        return $query->where('activitable_type', get_class($entity))
            ->where('activitable_id', $entity->getKey());
    }

    /**
     * Shape comun para frontend. Llamado desde DealController, CompanyController,
     * ContactController, ActivityController. Una sola fuente de verdad para que
     * todos los timelines reciban la misma estructura.
     */
    public function toPanelArray(): array
    {
        $quote = null;
        if ($this->related_quote_id) {
            $this->loadMissing('relatedQuote:id,slug,reference,name,status,grand_total,currency_code');
            if ($this->relatedQuote) {
                try {
                    $quoteUrl = route('business_management.quotes.show', $this->relatedQuote->slug);
                } catch (\Throwable $e) {
                    $quoteUrl = null;
                }
                $quote = [
                    'id'        => $this->relatedQuote->id,
                    'slug'      => $this->relatedQuote->slug,
                    'reference' => $this->relatedQuote->reference,
                    'name'      => $this->relatedQuote->name,
                    'status'    => $this->relatedQuote->status,
                    'total'     => $this->relatedQuote->grand_total,
                    'currency'  => $this->relatedQuote->currency_code,
                    'url'       => $quoteUrl,
                ];
            }
        }

        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'type'            => $this->type,
            'subject'         => $this->subject,
            'body'            => $this->body,
            'due_at'          => $this->due_at?->toIso8601String(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
            'outcome'         => $this->outcome,
            'duration_min'    => $this->duration_min,
            'location'        => $this->location,
            'priority'        => $this->priority,
            'attachment_path' => $this->attachment_path,
            'attachment_name' => $this->attachment_name,
            'related_quote_id'=> $this->related_quote_id,
            'related_quote'   => $quote,
            'is_overdue'      => $this->isOverdue(),
            'actor'           => $this->actor ? [
                'id'    => $this->actor->id,
                'name'  => $this->actor->name,
                'email' => $this->actor->email,
            ] : null,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
