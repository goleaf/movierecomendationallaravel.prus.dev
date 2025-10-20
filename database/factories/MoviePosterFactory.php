<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MoviePoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoviePoster>
 */
class MoviePosterFactory extends Factory
{
    protected $model = MoviePoster::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->imageUrl(600, 900, 'movies', true),
            'priority' => $this->faker->numberBetween(0, 100),
        ];
    }
}
