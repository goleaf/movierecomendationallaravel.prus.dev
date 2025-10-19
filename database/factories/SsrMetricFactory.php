<?php

namespace Database\Factories;

use App\Models\SsrMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SsrMetric>
 */
class SsrMetricFactory extends Factory
{
    protected $model = SsrMetric::class;

    public function definition(): array
    {
        return [
            'route' => $this->faker->url(),
            'score' => $this->faker->numberBetween(0, 100),
            'payload' => [],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
