<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id', 'sales_order_item_id', 'product_id', 'variant_id',
        'stock_lot_id', 'quantity',
    ];

    protected $casts = ['quantity' => 'decimal:4'];

    public function delivery(): BelongsTo { return $this->belongsTo(Delivery::class); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
}
