<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $genres = fake()->randomElements([
            'action',
            'adventure',
            'animation',
            'comedy',
            'crime',
            'drama',
            'fantasy',
            'history',
            'horror',
            'mystery',
            'romance',
            'science fiction',
            'thriller',
        ], fake()->numberBetween(1, 3));

        $releaseDate = fake()->dateTimeBetween('-10 years', 'now');

        return [
            'imdb_tt' => 'tt'.fake()->unique()->numerify('########'),
            'title' => $title,
            'plot' => fake()->paragraph(),
            'type' => fake()->randomElement(['movie', 'series', 'animation']),
            'year' => (int) $releaseDate->format('Y'),
            'release_date' => $releaseDate->format('Y-m-d'),
            'imdb_rating' => fake()->randomFloat(1, 5.0, 9.8),
            'imdb_votes' => fake()->numberBetween(2_500, 1_200_000),
            'runtime_min' => fake()->numberBetween(80, 160),
            'genres' => $genres,
            'poster_url' => fake()->imageUrl(600, 900, 'movie', true),
            'backdrop_url' => fake()->imageUrl(1280, 720, 'movie', true),
            'translations' => [
                'title' => [
                    'en' => $title,
                ],
                'plot' => [
                    'en' => fake()->paragraph(),
                ],
            ],
            'raw' => [
                'popularity' => fake()->randomFloat(2, 1, 100),
            ],
        ];
    }

    public function movie(): static
    {
        return $this->state(function (): array {
            $releaseDate = fake()->dateTimeBetween('-5 years', 'now');

            return [
                'type' => 'movie',
                'year' => (int) $releaseDate->format('Y'),
                'release_date' => $releaseDate->format('Y-m-d'),
                'genres' => ['drama', 'thriller'],
            ];
        });
    }

    public function series(): static
    {
        return $this->state(function (): array {
            $releaseDate = fake()->dateTimeBetween('-8 years', 'now');

            return [
                'type' => 'series',
                'year' => (int) $releaseDate->format('Y'),
                'release_date' => $releaseDate->format('Y-m-d'),
                'genres' => ['science fiction', 'mystery'],
            ];
        });
    }

    public function animation(): static
    {
        return $this->state(function (): array {
            $releaseDate = fake()->dateTimeBetween('-12 years', '-1 years');

            return [
                'type' => 'animation',
                'year' => (int) $releaseDate->format('Y'),
                'release_date' => $releaseDate->format('Y-m-d'),
                'genres' => ['animation', 'family'],
                'runtime_min' => fake()->numberBetween(70, 110),
            ];
        });
    }
}
