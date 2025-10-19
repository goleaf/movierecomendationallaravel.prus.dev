<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RecAbLog>
 */
class RecAbLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['home_feed', 'hero', 'details_sidebar']),
            'variant' => fake()->randomElement(['A', 'B']),
            'meta' => [
                'experiment' => fake()->randomElement(['rec-engine-v1', 'rec-engine-v2']),
                'score' => fake()->randomFloat(2, 0, 1),
            ],
        ];
    }
}
