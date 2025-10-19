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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/'.fake()->slug(),
            'score' => fake()->numberBetween(40, 100),
            'size' => fake()->numberBetween(100, 5000),
            'meta_count' => fake()->numberBetween(1, 20),
            'og_count' => fake()->numberBetween(0, 10),
            'ldjson_count' => fake()->numberBetween(0, 5),
            'img_count' => fake()->numberBetween(1, 50),
            'blocking_scripts' => fake()->numberBetween(0, 10),
        ];
    }
}
