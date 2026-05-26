<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tax_class_id', 'country_id', 'region_id', 'name', 'rate',
        'valid_from', 'valid_until', 'is_active', 'tenant_id', 'created_by',
    ];

    protected $casts = [
        'rate'        => 'decimal:4',
        'valid_from'  => 'date',
        'valid_until' => 'date',
        'is_active'   => 'boolean',
    ];

    public function taxClass(): BelongsTo { return $this->belongsTo(TaxClass::class); }
    public function country(): BelongsTo  { return $this->belongsTo(Country::class); }
}
