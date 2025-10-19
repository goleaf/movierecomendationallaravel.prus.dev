<?php

namespace Database\Seeders;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('movies')) {
            return;
        }

        Movie::query()->delete();

        $now = CarbonImmutable::now();

        $featured = [
            [
                'imdb_tt' => 'tt9000001',
                'title' => 'Nebula Rising',
                'plot' => 'A daring pilot leads a rescue mission across a collapsing wormhole.',
                'type' => 'movie',
                'year' => $now->subYears(1)->year,
                'release_date' => $now->subYears(1)->startOfMonth(),
                'imdb_rating' => 8.7,
                'imdb_votes' => 185_000,
                'runtime_min' => 128,
                'genres' => ['Sci-Fi', 'Adventure'],
            ],
            [
                'imdb_tt' => 'tt9000002',
                'title' => 'Metro Pulse',
                'plot' => 'Detectives race to stop a series of synchronized cyber heists.',
                'type' => 'movie',
                'year' => $now->subYears(2)->year,
                'release_date' => $now->subYears(2)->startOfMonth(),
                'imdb_rating' => 8.1,
                'imdb_votes' => 142_500,
                'runtime_min' => 117,
                'genres' => ['Thriller', 'Action'],
            ],
            [
                'imdb_tt' => 'tt9000003',
                'title' => 'Echoes of Winter',
                'plot' => 'An alpine village uncovers a buried secret when the snow melts early.',
                'type' => 'movie',
                'year' => $now->subYears(3)->year,
                'release_date' => $now->subYears(3)->startOfMonth(),
                'imdb_rating' => 7.9,
                'imdb_votes' => 96_300,
                'runtime_min' => 101,
                'genres' => ['Drama'],
            ],
            [
                'imdb_tt' => 'tt9000004',
                'title' => 'Solar Caravan',
                'plot' => 'A family of inventors tours festivals with a self-sustaining rover.',
                'type' => 'series',
                'year' => $now->subYears(1)->year,
                'release_date' => $now->subYears(1)->startOfYear(),
                'imdb_rating' => 8.5,
                'imdb_votes' => 52_000,
                'runtime_min' => 52,
                'genres' => ['Documentary', 'Adventure'],
            ],
            [
                'imdb_tt' => 'tt9000005',
                'title' => 'Harbor Lights',
                'plot' => 'A quiet seaside town becomes a haven for runaway innovators.',
                'type' => 'mini-series',
                'year' => $now->subYears(4)->year,
                'release_date' => $now->subYears(4)->startOfMonth(),
                'imdb_rating' => 8.3,
                'imdb_votes' => 78_000,
                'runtime_min' => 55,
                'genres' => ['Drama', 'Romance'],
            ],
            [
                'imdb_tt' => 'tt9000006',
                'title' => 'Quantum Alley',
                'plot' => 'Two rival researchers share a lab after a funding shake-up.',
                'type' => 'series',
                'year' => $now->subYears(2)->year,
                'release_date' => $now->subYears(2)->startOfYear(),
                'imdb_rating' => 8.9,
                'imdb_votes' => 204_000,
                'runtime_min' => 48,
                'genres' => ['Sci-Fi', 'Drama'],
            ],
            [
                'imdb_tt' => 'tt9000007',
                'title' => 'Crimson Atlas',
                'plot' => 'A cartographer maps the dreams of an entire metropolis.',
                'type' => 'movie',
                'year' => $now->subYears(1)->year,
                'release_date' => $now->subMonths(9)->startOfDay(),
                'imdb_rating' => 8.2,
                'imdb_votes' => 121_000,
                'runtime_min' => 124,
                'genres' => ['Fantasy', 'Drama'],
            ],
            [
                'imdb_tt' => 'tt9000008',
                'title' => 'Aurora Station',
                'plot' => 'The last train to the Arctic outpost arrives without a crew.',
                'type' => 'movie',
                'year' => $now->subYears(1)->year,
                'release_date' => $now->subMonths(6)->startOfDay(),
                'imdb_rating' => 8.4,
                'imdb_votes' => 132_000,
                'runtime_min' => 111,
                'genres' => ['Mystery', 'Thriller'],
            ],
        ];

        foreach ($featured as $attributes) {
            Movie::factory()->create($attributes);
        }

        Movie::factory()->count(16)->create();
    }
}
