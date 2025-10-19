<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeviceHistory;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DeviceHistory>
 */
class DeviceHistoryFactory extends Factory
{
    protected $model = DeviceHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $viewedAt = Carbon::instance(fake()->dateTimeBetween('-7 days', 'now'));

        return [
            'device_id' => 'd_'.fake()->uuid(),
            'placement' => fake()->randomElement(['home', 'show', 'trends']),
            'movie_id' => Movie::factory(),
            'viewed_at' => $viewedAt->format('Y-m-d H:i:s'),
        ];
    }
}
