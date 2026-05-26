<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'slug'      => Str::random(22),
            'code'      => 'WH-' . strtoupper(Str::random(6)),
            'name'      => $this->faker->unique()->words(2, true),
            'description' => fake()->optional(0.7)->sentence(8),
            'type'      => 'main',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /** Helper para tests que necesitan un nombre específico (asserts por nombre). */
    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /** Helper para crear warehouses inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
