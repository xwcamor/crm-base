<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    protected $fillable = [
        'purchase_receipt_id', 'purchase_order_item_id', 'product_id', 'variant_id',
        'stock_lot_id', 'quantity_received', 'quantity_rejected', 'rejection_reason',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'quantity_rejected' => 'decimal:4',
    ];

    public function purchaseReceipt(): BelongsTo { return $this->belongsTo(PurchaseReceipt::class); }
    public function product(): BelongsTo         { return $this->belongsTo(Product::class); }
}
