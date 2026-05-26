<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRevision extends Model
{
    protected $fillable = ['quote_id', 'revision_number', 'snapshot', 'created_by'];

    protected $casts = [
        'snapshot' => 'array',
        'revision_number' => 'integer',
    ];

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
}
