<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealContact extends Model
{
    protected $fillable = ['deal_id', 'contact_id', 'role', 'is_primary', 'notes', 'created_by'];

    protected $casts = ['is_primary' => 'boolean'];

    public function deal(): BelongsTo    { return $this->belongsTo(Deal::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
}
