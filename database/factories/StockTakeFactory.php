<?php

namespace Database\Factories;

use App\Models\StockTake;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * StockTakeFactory — factory minimal para tests.
 *
 * No setea warehouse_id porque es FK obligatoria — los tests la inyectan al
 * construir.
 */
class StockTakeFactory extends Factory
{
    protected $model = StockTake::class;

    public function definition(): array
    {
        return [
            'slug'      => Str::random(22),
            'reference' => 'COUNT-' . now()->year . '-' . strtoupper(Str::random(6)),
            'status'    => 'draft',
        ];
    }
}
