<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\RecAbLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecAbLog>
 */
class RecAbLogFactory extends Factory
{
    protected $model = RecAbLog::class;

    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['homepage', 'details', 'continue-watching']),
            'variant' => fake()->randomElement(['A', 'B']),
            'movie_id' => Movie::factory(),
            'meta' => [
                'experiment' => fake()->slug(2),
                'position' => fake()->numberBetween(1, 20),
            ],
        ];
    }
}
