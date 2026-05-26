<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory minima de Quote para tests. Solo header — los items se crean
 * por separado en el test segun necesidad.
 *
 * NO crea Company/Deal autoreferenciados — el TestCase los seedea.
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'slug'           => Str::random(22),
            'prefix'         => 'COT',
            'reference'      => 'COT-' . now()->year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'company_id'     => 1,
            'status'         => 'draft',
            'issue_date'     => now()->toDateString(),
            'valid_until'    => now()->addDays(30)->toDateString(),
            'currency_code'  => 'USD',
            'subtotal'       => 0,
            'discount_total' => 0,
            'tax_total'      => 0,
            'shipping_cost'  => 0,
            'grand_total'    => 0,
            'is_active'      => true,
        ];
    }

    public function withReference(string $reference): self
    {
        return $this->state(fn () => ['reference' => $reference]);
    }

    public function status(string $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
