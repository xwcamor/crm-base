<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    protected $fillable = [
        'price_list_id', 'product_id', 'variant_id',
        'price', 'discount_pct', 'min_quantity', 'is_active', 'created_by',
    ];

    protected $casts = [
        'price'        => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'min_quantity' => 'decimal:4',
        'is_active'    => 'boolean',
    ];

    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
}
