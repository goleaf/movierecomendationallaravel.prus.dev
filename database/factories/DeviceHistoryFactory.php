<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeviceHistory;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceHistory>
 */
final class DeviceHistoryFactory extends Factory
{
    protected $model = DeviceHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'placement' => fake()->randomElement(['home', 'show', 'trends']),
            'movie_id' => Movie::factory(),
            'viewed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
