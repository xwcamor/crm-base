<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * PurchaseOrderFactory — factory minimal para tests.
 *
 * No setea supplier_company_id / warehouse_id porque son FK obligatorias —
 * los tests las inyectan al construir. Reference es nullable a nivel BD pero
 * habitualmente la genera el service via nextReference().
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'slug'        => Str::random(22),
            'prefix'      => 'PO',
            'reference'   => 'PO-' . now()->year . '-' . strtoupper(Str::random(6)),
            'status'      => 'draft',
            'order_date'  => now()->toDateString(),
            'subtotal'    => 0,
            'tax_total'   => 0,
            'grand_total' => 0,
        ];
    }
}
