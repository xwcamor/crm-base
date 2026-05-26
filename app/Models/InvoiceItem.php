<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'variant_id', 'sales_order_item_id',
        'name', 'description', 'sku',
        'quantity', 'unit_price', 'discount_pct',
        'tax_class_id', 'tax_pct',
        'line_subtotal', 'line_tax', 'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'unit_price'    => 'decimal:4',
        'discount_pct'  => 'decimal:2',
        'tax_pct'       => 'decimal:4',
        'line_subtotal' => 'decimal:2',
        'line_tax'      => 'decimal:2',
        'line_total'    => 'decimal:2',
        'sort_order'    => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
