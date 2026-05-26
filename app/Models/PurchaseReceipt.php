<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'slug', 'reference', 'purchase_order_id', 'warehouse_id',
        'received_at', 'supplier_invoice_number', 'carrier', 'tracking_number',
        'variance_note', 'status',
        'tenant_id', 'created_by',
    ];

    protected $casts = ['received_at' => 'datetime'];

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

    public function items(): HasMany           { return $this->hasMany(PurchaseReceiptItem::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function warehouse(): BelongsTo     { return $this->belongsTo(Warehouse::class); }
}
