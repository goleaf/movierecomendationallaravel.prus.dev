<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\RecClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecClick>
 */
class RecClickFactory extends Factory
{
    protected $model = RecClick::class;

    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['homepage', 'details', 'continue-watching']),
            'variant' => fake()->randomElement(['A', 'B']),
            'source' => fake()->optional()->randomElement(['recommendation', 'search', 'email']),
            'movie_id' => Movie::factory(),
        ];
    }
}
