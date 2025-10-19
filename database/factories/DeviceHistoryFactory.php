<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeviceHistory;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceHistory>
 */
class DeviceHistoryFactory extends Factory
{
    protected $model = DeviceHistory::class;

    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['homepage', 'details', 'continue-watching']),
            'movie_id' => Movie::factory(),
            'viewed_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
        ];
    }
}
