<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockLot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'slug', 'product_id', 'variant_id', 'warehouse_id',
        'lot_number', 'serial_number', 'qty_initial', 'qty_remaining',
        'manufactured_at', 'expires_at', 'unit_cost',
        'source_purchase_order_id', 'source_supplier_id',
        'is_active', 'tenant_id', 'created_by',
    ];

    protected $casts = [
        'qty_initial'    => 'decimal:4',
        'qty_remaining'  => 'decimal:4',
        'unit_cost'      => 'decimal:4',
        'manufactured_at'=> 'date',
        'expires_at'     => 'date',
        'is_active'      => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->slug)) {
                do { $slug = Str::random(22); } while (static::where('slug', $slug)->exists());
                $m->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
