<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\RecAbLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecAbLog>
 */
final class RecAbLogFactory extends Factory
{
    protected $model = RecAbLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['home', 'show', 'trends']),
            'variant' => fake()->randomElement(['A', 'B']),
            'movie_id' => Movie::factory(),
            'meta' => [
                'session' => fake()->uuid(),
            ],
        ];
    }
}
