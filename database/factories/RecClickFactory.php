<?php

namespace Database\Factories;

use App\Models\RecClick;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RecClick>
 */
class RecClickFactory extends Factory
{
    protected $model = RecClick::class;

    public function definition(): array
    {
        return [
            'movie_id' => null,
            'device_id' => $this->faker->uuid(),
            'placement' => $this->faker->randomElement(['home', 'show', 'trends']),
            'variant' => $this->faker->randomElement(['A', 'B']),
            'source' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
