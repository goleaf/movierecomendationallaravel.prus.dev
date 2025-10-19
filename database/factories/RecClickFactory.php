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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'device_id' => 'd_'.fake()->uuid(),
            'placement' => fake()->randomElement(['home', 'show', 'trends']),
            'variant' => fake()->randomElement(['A', 'B']),
            'source' => fake()->optional()->randomElement(['email', 'push', 'organic']),
        ];
    }
}
