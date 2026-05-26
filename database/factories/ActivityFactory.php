<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(Activity::TYPES);

        return [
            'slug'    => Str::random(22),
            'type'    => $type,
            'subject' => $this->faker->sentence(4),
            'body'    => $this->faker->paragraph(2),
        ];
    }

    public function note(): self
    {
        return $this->state(['type' => 'note', 'subject' => null]);
    }

    public function call(): self
    {
        return $this->state([
            'type'         => 'call',
            'outcome'      => 'answered',
            'duration_min' => 5,
        ]);
    }

    public function email(): self
    {
        return $this->state(['type' => 'email']);
    }

    public function meeting(): self
    {
        return $this->state([
            'type'         => 'meeting',
            'due_at'       => now()->addDays(2),
            'duration_min' => 30,
        ]);
    }

    public function task(): self
    {
        return $this->state([
            'type'     => 'task',
            'due_at'   => now()->addDay(),
            'priority' => 'medium',
        ]);
    }

    public function completed(): self
    {
        return $this->state(['completed_at' => now()]);
    }

    public function pending(): self
    {
        return $this->state(['completed_at' => null]);
    }
}
