<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SsrMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SsrMetric>
 */
class SsrMetricFactory extends Factory
{
    protected $model = SsrMetric::class;

    public function definition(): array
    {
        return [
            'path' => '/'.fake()->slug(),
            'score' => fake()->numberBetween(0, 100),
            'size' => fake()->numberBetween(10_000, 1_000_000),
            'meta_count' => fake()->numberBetween(1, 25),
            'og_count' => fake()->numberBetween(0, 10),
            'ldjson_count' => fake()->numberBetween(0, 10),
            'img_count' => fake()->numberBetween(0, 30),
            'blocking_scripts' => fake()->numberBetween(0, 5),
            'first_byte_ms' => fake()->numberBetween(0, 1500),
        ];
    }
}
