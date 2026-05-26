<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeLine extends Model
{
    protected $fillable = [
        'stock_take_id', 'product_id', 'variant_id',
        'qty_system', 'qty_counted', 'variance', 'note',
    ];

    protected $casts = [
        'qty_system'  => 'decimal:4',
        'qty_counted' => 'decimal:4',
        'variance'    => 'decimal:4',
    ];

    public function stockTake(): BelongsTo { return $this->belongsTo(StockTake::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
}
