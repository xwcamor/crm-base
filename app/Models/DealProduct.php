<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealProduct extends Model
{
    protected $fillable = [
        'deal_id', 'product_id', 'name', 'description',
        'quantity', 'unit_price', 'discount_pct', 'line_total',
        'sort_order', 'created_by',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'unit_price'   => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'line_total'   => 'decimal:2',
        'sort_order'   => 'integer',
    ];

    public function deal(): BelongsTo    { return $this->belongsTo(Deal::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
