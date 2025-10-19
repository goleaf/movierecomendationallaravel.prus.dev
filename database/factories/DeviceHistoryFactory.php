<?php

namespace Database\Factories;

use App\Models\DeviceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DeviceHistory>
 */
class DeviceHistoryFactory extends Factory
{
    protected $model = DeviceHistory::class;

    public function definition(): array
    {
        return [
            'device_id' => $this->faker->uuid(),
            'placement' => $this->faker->randomElement(['home', 'show', 'trends']),
            'movie_id' => null,
            'viewed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
