<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'imdb_tt' => 'tt'.str_pad((string) $this->faker->numberBetween(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(3),
            'plot' => $this->faker->paragraph(),
            'type' => 'movie',
            'year' => $this->faker->numberBetween(1990, (int) now()->year),
            'release_date' => $this->faker->date(),
            'imdb_rating' => $this->faker->randomFloat(1, 5, 9.5),
            'imdb_votes' => $this->faker->numberBetween(1_000, 250_000),
            'runtime_min' => $this->faker->numberBetween(80, 160),
            'genres' => $this->faker->randomElements([
                'Action',
                'Drama',
                'Sci-Fi',
                'Comedy',
                'Thriller',
                'Adventure',
            ], 2),
            'poster_url' => $this->faker->imageUrl(500, 750, 'movie', true),
            'backdrop_url' => $this->faker->imageUrl(1280, 720, 'movie', true),
            'translations' => [
                'ru' => [
                    'title' => $this->faker->sentence(3),
                    'plot' => $this->faker->paragraph(),
                ],
            ],
            'raw' => [
                'source' => 'factory',
                'uid' => (string) Str::uuid(),
            ],
        ];
    }
}
