<?php

namespace Database\Factories;

use App\Models\RecAbLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RecAbLog>
 */
class RecAbLogFactory extends Factory
{
    protected $model = RecAbLog::class;

    public function definition(): array
    {
        return [
            'device_id' => $this->faker->uuid(),
            'placement' => $this->faker->randomElement(['home', 'show', 'trends']),
            'variant' => $this->faker->randomElement(['A', 'B']),
            'movie_id' => null,
            'meta' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
