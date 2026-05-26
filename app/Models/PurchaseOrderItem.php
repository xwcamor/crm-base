<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'variant_id',
        'name', 'description',
        'quantity_ordered', 'quantity_received',
        'unit_cost', 'discount_pct', 'tax_class_id', 'tax_pct',
        'line_subtotal', 'line_tax', 'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'discount_pct'      => 'decimal:2',
        'tax_pct'           => 'decimal:4',
        'line_subtotal'     => 'decimal:2',
        'line_tax'          => 'decimal:2',
        'line_total'        => 'decimal:2',
        'sort_order'        => 'integer',
    ];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function product(): BelongsTo       { return $this->belongsTo(Product::class); }
}
