<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'warehouse_id', 'product_id', 'variant_id',
        'qty_on_hand', 'qty_reserved', 'qty_incoming',
        'average_cost', 'last_movement_at',
        'tenant_id',
    ];

    protected $casts = [
        'qty_on_hand'      => 'decimal:4',
        'qty_reserved'     => 'decimal:4',
        'qty_incoming'     => 'decimal:4',
        'average_cost'     => 'decimal:4',
        'last_movement_at' => 'datetime',
    ];

    public function getQtyAvailableAttribute(): float
    {
        return (float) $this->qty_on_hand - (float) $this->qty_reserved;
    }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
