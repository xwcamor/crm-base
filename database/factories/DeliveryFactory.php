<?php

namespace Database\Factories;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * DeliveryFactory — factory minimal para tests.
 *
 * No setea sales_order_id / warehouse_id porque son FK obligatorias — los
 * tests las inyectan al construir.
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'slug'      => Str::random(22),
            'prefix'    => 'DEL',
            'reference' => 'DEL-' . now()->year . '-' . strtoupper(Str::random(6)),
            'status'    => 'pending',
        ];
    }
}
