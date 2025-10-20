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
        $bytes = fake()->numberBetween(10_000, 1_000_000);

        $metaCount = fake()->numberBetween(1, 25);
        $ogCount = fake()->numberBetween(0, 10);
        $ldjsonCount = fake()->numberBetween(0, 10);

        return [
            'path' => '/'.fake()->slug(),
            'score' => fake()->numberBetween(0, 100),
            'size' => $bytes,
            'html_bytes' => $bytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => fake()->numberBetween(0, 30),
            'blocking_scripts' => fake()->numberBetween(0, 5),
            'first_byte_ms' => fake()->numberBetween(0, 1500),
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
            'recorded_at' => now(),
            'collected_at' => now(),
        ];
    }
}
