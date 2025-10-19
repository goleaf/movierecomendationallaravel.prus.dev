<?php

declare(strict_types=1);

namespace Tests\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now()->toImmutable();

        $movies = [
            [
                'id' => 1,
                'imdb_tt' => 'tt8000001',
                'title' => 'The Quantum Enigma',
                'plot' => 'A physicist discovers an anomaly that rewrites the laws of time.',
                'type' => 'movie',
                'year' => 2024,
                'release_date' => $now->subMonths(2)->format('Y-m-d'),
                'imdb_rating' => 8.8,
                'imdb_votes' => 120_000,
                'runtime_min' => 142,
                'genres' => ['Sci-Fi', 'Thriller'],
                'poster_url' => 'https://example.com/posters/quantum.jpg',
                'backdrop_url' => 'https://example.com/backdrops/quantum.jpg',
                'translations' => [
                    'title' => [
                        'ru' => 'Квантовая Загадка',
                    ],
                    'plot' => [
                        'ru' => 'Учёная обнаруживает аномалию, меняющую ход времени.',
                    ],
                ],
                'raw' => ['source' => 'seed'],
                'created_at' => $now->subMonths(2),
                'updated_at' => $now->subMonths(2),
            ],
            [
                'id' => 2,
                'imdb_tt' => 'tt8000002',
                'title' => 'Solaris Rising',
                'plot' => 'A rescue crew uncovers a sentient storm orbiting a dying star.',
                'type' => 'movie',
                'year' => 2023,
                'release_date' => $now->subMonths(8)->format('Y-m-d'),
                'imdb_rating' => 8.1,
                'imdb_votes' => 95_000,
                'runtime_min' => 128,
                'genres' => ['Adventure', 'Drama'],
                'poster_url' => 'https://example.com/posters/solaris.jpg',
                'backdrop_url' => 'https://example.com/backdrops/solaris.jpg',
                'translations' => [
                    'title' => [
                        'ru' => 'Восход Соляриса',
                    ],
                    'plot' => [
                        'ru' => 'Команда спасателей сталкивается с разумной бурей.',
                    ],
                ],
                'raw' => ['source' => 'seed'],
                'created_at' => $now->subMonths(8),
                'updated_at' => $now->subMonths(8),
            ],
            [
                'id' => 3,
                'imdb_tt' => 'tt8000003',
                'title' => 'Nebula Drift',
                'plot' => 'Pilots navigate an expanding nebula threatening to swallow trade routes.',
                'type' => 'movie',
                'year' => 2022,
                'release_date' => $now->subYears(1)->format('Y-m-d'),
                'imdb_rating' => 7.6,
                'imdb_votes' => 180_000,
                'runtime_min' => 116,
                'genres' => ['Action', 'Sci-Fi'],
                'poster_url' => 'https://example.com/posters/nebula.jpg',
                'backdrop_url' => 'https://example.com/backdrops/nebula.jpg',
                'translations' => [
                    'title' => [
                        'ru' => 'Туманность в Дрейфе',
                    ],
                    'plot' => [
                        'ru' => 'Пилоты пытаются обойти расширяющуюся туманность.',
                    ],
                ],
                'raw' => ['source' => 'seed'],
                'created_at' => $now->subYears(1),
                'updated_at' => $now->subYears(1),
            ],
        ];

        foreach ($movies as $movie) {
            Movie::query()->create($movie);
        }

        $impressionLogs = collect([
            ['device_id' => 'device-even-2', 'variant' => 'A'],
            ['device_id' => 'device-odd', 'variant' => 'B'],
            ['device_id' => 'device-alt-1', 'variant' => 'A'],
            ['device_id' => 'device-alt-2', 'variant' => 'B'],
            ['device_id' => 'device-alt-3', 'variant' => 'A'],
            ['device_id' => 'device-alt-4', 'variant' => 'A'],
        ])->map(function (array $row) use ($now): array {
            return [
                'device_id' => $row['device_id'],
                'variant' => $row['variant'],
                'placement' => 'home',
                'created_at' => $now->subDays(2),
                'updated_at' => $now->subDays(2),
            ];
        });

        DB::table('rec_ab_logs')->insert($impressionLogs->all());

        $clicks = [
            ['movie_id' => 1, 'variant' => 'A', 'placement' => 'home', 'offset' => 1],
            ['movie_id' => 1, 'variant' => 'A', 'placement' => 'show', 'offset' => 2],
            ['movie_id' => 2, 'variant' => 'B', 'placement' => 'home', 'offset' => 3],
            ['movie_id' => 3, 'variant' => 'A', 'placement' => 'trends', 'offset' => 1],
            ['movie_id' => 2, 'variant' => 'B', 'placement' => 'trends', 'offset' => 2],
            ['movie_id' => 1, 'variant' => 'A', 'placement' => 'home', 'offset' => 5],
        ];

        DB::table('rec_clicks')->insert(array_map(function (array $row) use ($now): array {
            return [
                'movie_id' => $row['movie_id'],
                'device_id' => 'device-seed',
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'created_at' => $now->subDays($row['offset']),
                'updated_at' => $now->subDays($row['offset']),
            ];
        }, $clicks));

        $views = [
            ['device_id' => 'device-even-2', 'path' => '/'],
            ['device_id' => 'device-even-2', 'path' => '/movies/1'],
            ['device_id' => 'device-odd', 'path' => '/'],
            ['device_id' => 'device-odd', 'path' => '/trends'],
        ];

        DB::table('device_history')->insert(array_map(function (array $row) use ($now): array {
            return [
                'device_id' => $row['device_id'],
                'path' => $row['path'],
                'viewed_at' => $now->subDays(1),
                'created_at' => $now->subDays(1),
                'updated_at' => $now->subDays(1),
            ];
        }, $views));

        $ssrMetrics = [
            ['path' => '/', 'score' => 85, 'delta' => 1, 'size' => 410000, 'meta' => 18, 'og' => 2, 'ld' => 1, 'img' => 15, 'blocking' => 2, 'first_byte' => 240],
            ['path' => '/trends', 'score' => 78, 'delta' => 1, 'size' => 395000, 'meta' => 16, 'og' => 2, 'ld' => 0, 'img' => 14, 'blocking' => 1, 'first_byte' => 260],
            ['path' => '/', 'score' => 90, 'delta' => 0, 'size' => 360000, 'meta' => 20, 'og' => 3, 'ld' => 1, 'img' => 12, 'blocking' => 1, 'first_byte' => 190],
            ['path' => '/trends', 'score' => 72, 'delta' => 0, 'size' => 405000, 'meta' => 15, 'og' => 2, 'ld' => 0, 'img' => 16, 'blocking' => 3, 'first_byte' => 280],
        ];

        $metricColumns = [
            'collected_at' => Schema::hasColumn('ssr_metrics', 'collected_at'),
            'html_bytes' => Schema::hasColumn('ssr_metrics', 'html_bytes'),
            'size' => Schema::hasColumn('ssr_metrics', 'size'),
            'first_byte_ms' => Schema::hasColumn('ssr_metrics', 'first_byte_ms'),
            'has_json_ld' => Schema::hasColumn('ssr_metrics', 'has_json_ld'),
            'has_open_graph' => Schema::hasColumn('ssr_metrics', 'has_open_graph'),
            'meta' => Schema::hasColumn('ssr_metrics', 'meta'),
        ];

        DB::table('ssr_metrics')->insert(array_map(function (array $row) use ($metricColumns, $now): array {
            $ts = $now->subDays($row['delta']);

            $data = [
                'path' => $row['path'],
                'score' => $row['score'],
                'meta_count' => $row['meta'],
                'og_count' => $row['og'],
                'ldjson_count' => $row['ld'],
                'img_count' => $row['img'],
                'blocking_scripts' => $row['blocking'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];

            if ($metricColumns['collected_at']) {
                $data['collected_at'] = $ts;
            }

            if ($metricColumns['size']) {
                $data['size'] = $row['size'];
            }

            if ($metricColumns['html_bytes']) {
                $data['html_bytes'] = $row['size'];
            }

            if ($metricColumns['first_byte_ms']) {
                $data['first_byte_ms'] = $row['first_byte'];
            }

            if ($metricColumns['has_json_ld']) {
                $data['has_json_ld'] = $row['ld'] > 0;
            }

            if ($metricColumns['has_open_graph']) {
                $data['has_open_graph'] = $row['og'] > 0;
            }

            if ($metricColumns['meta']) {
                $data['meta'] = null;
            }

            return $data;
        }, $ssrMetrics));
    }
}
