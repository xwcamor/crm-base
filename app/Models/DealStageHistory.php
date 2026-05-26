<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealStageHistory extends Model
{
    protected $table = 'deal_stage_history';

    public $timestamps = false;

    protected $fillable = [
        'deal_id', 'from_stage_id', 'to_stage_id', 'changed_by',
        'changed_at', 'days_in_previous_stage', 'note',
    ];

    protected $casts = [
        'changed_at'             => 'datetime',
        'days_in_previous_stage' => 'integer',
    ];

    public function deal(): BelongsTo      { return $this->belongsTo(Deal::class); }
    public function fromStage(): BelongsTo { return $this->belongsTo(PipelineStage::class, 'from_stage_id'); }
    public function toStage(): BelongsTo   { return $this->belongsTo(PipelineStage::class, 'to_stage_id'); }
    public function changer(): BelongsTo   { return $this->belongsTo(User::class, 'changed_by'); }
}
