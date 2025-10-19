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
        $metaCount = fake()->numberBetween(1, 25);
        $ogCount = fake()->numberBetween(0, 10);
        $ldjsonCount = fake()->numberBetween(0, 10);
        $imgCount = fake()->numberBetween(0, 30);
        $blocking = fake()->numberBetween(0, 5);
        $firstByte = fake()->numberBetween(0, 1500);

        return [
            'path' => '/'.fake()->slug(),
            'score' => fake()->numberBetween(0, 100),
            'recorded_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'payload' => [
                'html_size' => fake()->numberBetween(10_000, 1_000_000),
                'counts' => [
                    'meta' => $metaCount,
                    'og' => $ogCount,
                    'ldjson' => $ldjsonCount,
                    'img' => $imgCount,
                    'blocking_scripts' => $blocking,
                ],
                'first_byte_ms' => $firstByte,
            ],
        ];
    }
}
