<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DeviceHistory>
 */
class DeviceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->uuid(),
            'movie_id' => fake()->boolean(80) ? Movie::factory() : null,
            'placement' => fake()->optional()->randomElement(['home_feed', 'hero', 'details_sidebar']),
            'viewed_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
        ];
    }
}
