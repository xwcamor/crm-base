<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory minima de SalesOrder para tests. Solo header — los items se
 * crean por separado en el test segun necesidad (varios tests no requieren
 * lineas para verificar tenant isolation, soft-delete, etc.).
 *
 * NO crea Company/Warehouse autoreferenciados — el TestCase los seedea.
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'slug'           => Str::random(22),
            'prefix'         => 'OV',
            'reference'      => 'OV-' . now()->year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'company_id'     => 1,
            'warehouse_id'   => 1,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'order_date'     => now()->toDateString(),
            'currency_code'  => 'USD',
            'subtotal'       => 0,
            'discount_total' => 0,
            'tax_total'      => 0,
            'shipping_cost'  => 0,
            'grand_total'    => 0,
        ];
    }

    public function withReference(string $reference): self
    {
        return $this->state(fn () => ['reference' => $reference]);
    }
}
