<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealCompetitor extends Model
{
    protected $fillable = [
        'deal_id', 'competitor_company_id', 'competitor_name',
        'status', 'strengths', 'weaknesses', 'notes', 'created_by',
    ];

    public function deal(): BelongsTo       { return $this->belongsTo(Deal::class); }
    public function competitor(): BelongsTo { return $this->belongsTo(Company::class, 'competitor_company_id'); }
}
