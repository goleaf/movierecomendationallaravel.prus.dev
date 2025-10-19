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

    public function definition(): array
    {
        $title = rtrim($this->faker->unique()->sentence(3), '.');
        $releaseDate = $this->faker->dateTimeBetween('-10 years', 'now');
        $genresPool = ['Drama', 'Comedy', 'Sci-Fi', 'Action', 'Thriller', 'Romance', 'Animation', 'Documentary'];
        $genres = collect($genresPool)
            ->shuffle()
            ->take($this->faker->numberBetween(1, 3))
            ->values()
            ->all();
        $imdbVotes = $this->faker->numberBetween(5_000, 250_000);
        $posterSeed = Str::slug($title.'-'.$this->faker->unique()->numberBetween(1, 9999));

        return [
            'imdb_tt' => $this->faker->unique()->regexify('tt\d{7}'),
            'title' => $title,
            'plot' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['movie', 'series', 'mini-series', 'documentary']),
            'year' => (int) $releaseDate->format('Y'),
            'release_date' => $releaseDate,
            'imdb_rating' => $this->faker->randomFloat(1, 6.2, 9.8),
            'imdb_votes' => $imdbVotes,
            'runtime_min' => $this->faker->numberBetween(75, 160),
            'genres' => $genres,
            'poster_url' => 'https://picsum.photos/seed/'.$posterSeed.'/400/600',
            'backdrop_url' => 'https://picsum.photos/seed/'.$posterSeed.'/1280/720',
            'translations' => [
                'ru' => [
                    'title' => $title,
                    'plot' => $this->faker->sentence(12),
                ],
            ],
            'raw' => [
                'source' => 'factory',
                'popularity' => $this->faker->randomFloat(2, 1, 100),
            ],
        ];
    }
}
