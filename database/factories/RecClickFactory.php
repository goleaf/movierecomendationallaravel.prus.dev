<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\RecClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecClick>
 */
final class RecClickFactory extends Factory
{
    protected $model = RecClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['home', 'show', 'trends']),
            'variant' => fake()->randomElement(['A', 'B']),
            'source' => fake()->randomElement(['web', 'app', null]),
        ];
    }
}
