<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $featured = [
            [
                'imdb_tt' => 'tt9000001',
                'title' => 'Galactic Frontiers',
                'plot' => 'An unlikely crew charts the edge of the galaxy in search of a new home for humanity.',
                'type' => 'movie',
                'year' => 2024,
                'release_date' => '2024-05-18',
                'imdb_rating' => 8.6,
                'imdb_votes' => 125_430,
                'runtime_min' => 132,
                'genres' => ['science fiction', 'adventure'],
                'poster_url' => 'https://picsum.photos/seed/galactic-frontiers/600/900',
                'backdrop_url' => 'https://picsum.photos/seed/galactic-frontiers/1280/720',
            ],
            [
                'imdb_tt' => 'tt9000002',
                'title' => 'Mysteries of Everspring',
                'plot' => 'A small-town detective unravels a web of secrets after a string of strange disappearances.',
                'type' => 'series',
                'year' => 2023,
                'release_date' => '2023-09-07',
                'imdb_rating' => 8.2,
                'imdb_votes' => 87_912,
                'runtime_min' => 52,
                'genres' => ['mystery', 'drama', 'thriller'],
                'poster_url' => 'https://picsum.photos/seed/mysteries-everspring/600/900',
                'backdrop_url' => 'https://picsum.photos/seed/mysteries-everspring/1280/720',
            ],
            [
                'imdb_tt' => 'tt9000003',
                'title' => 'Laughing Matters',
                'plot' => 'Three stand-up comics tour the world discovering that laughter is a universal language.',
                'type' => 'movie',
                'year' => 2022,
                'release_date' => '2022-11-25',
                'imdb_rating' => 7.5,
                'imdb_votes' => 64_001,
                'runtime_min' => 108,
                'genres' => ['comedy'],
                'poster_url' => 'https://picsum.photos/seed/laughing-matters/600/900',
                'backdrop_url' => 'https://picsum.photos/seed/laughing-matters/1280/720',
            ],
            [
                'imdb_tt' => 'tt9000004',
                'title' => 'Echoes of the Deep',
                'plot' => 'Marine biologists confront a mysterious signal originating from the ocean floor.',
                'type' => 'movie',
                'year' => 2021,
                'release_date' => '2021-03-14',
                'imdb_rating' => 8.1,
                'imdb_votes' => 142_307,
                'runtime_min' => 118,
                'genres' => ['thriller', 'science fiction'],
                'poster_url' => 'https://picsum.photos/seed/echoes-deep/600/900',
                'backdrop_url' => 'https://picsum.photos/seed/echoes-deep/1280/720',
            ],
            [
                'imdb_tt' => 'tt9000005',
                'title' => 'Song of the Ember',
                'plot' => 'A gifted bard must unite divided kingdoms through a melody that could reignite magic.',
                'type' => 'movie',
                'year' => 2020,
                'release_date' => '2020-08-02',
                'imdb_rating' => 8.8,
                'imdb_votes' => 204_512,
                'runtime_min' => 145,
                'genres' => ['fantasy', 'adventure'],
                'poster_url' => 'https://picsum.photos/seed/song-ember/600/900',
                'backdrop_url' => 'https://picsum.photos/seed/song-ember/1280/720',
            ],
        ];

        foreach ($featured as $movieData) {
            Movie::query()->create(array_merge($movieData, [
                'translations' => [
                    'title' => [
                        'en' => $movieData['title'],
                    ],
                    'plot' => [
                        'en' => $movieData['plot'],
                    ],
                ],
                'raw' => ['source' => 'seed'],
            ]));
        }

        Movie::factory()
            ->count(40)
            ->create();
    }
}
