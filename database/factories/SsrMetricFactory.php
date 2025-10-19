<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SsrMetric>
 */
class SsrMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => fake()->url(),
            'score' => fake()->numberBetween(0, 100),
            'size' => fake()->numberBetween(10_000, 250_000),
            'meta_count' => fake()->numberBetween(5, 60),
            'og_count' => fake()->numberBetween(0, 20),
            'ldjson_count' => fake()->numberBetween(0, 10),
            'img_count' => fake()->numberBetween(1, 40),
            'blocking_scripts' => fake()->numberBetween(0, 10),
        ];
    }
}
