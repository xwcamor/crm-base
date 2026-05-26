<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'warehouse_id', 'product_id', 'variant_id',
        'type', 'quantity', 'unit_cost', 'total_cost',
        'source_type', 'source_id', 'source_reference',
        'stock_lot_id', 'note', 'moved_at',
        'tenant_id', 'created_by',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:4',
        'moved_at'   => 'datetime',
    ];

    public const TYPES = ['receipt', 'issue', 'transfer_in', 'transfer_out', 'adjustment', 'return_in', 'return_out'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
}
