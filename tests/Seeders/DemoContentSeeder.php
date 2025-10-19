<?php

declare(strict_types=1);

namespace Tests\Seeders;

use App\Models\Movie;
use App\Support\SsrMetricPayload;
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
            ['movie_id' => 1, 'device_id' => 'device-even-2', 'variant' => 'A'],
            ['movie_id' => 2, 'device_id' => 'device-odd', 'variant' => 'B'],
            ['movie_id' => 3, 'device_id' => 'device-alt-1', 'variant' => 'A'],
            ['movie_id' => 1, 'device_id' => 'device-alt-2', 'variant' => 'B'],
            ['movie_id' => 2, 'device_id' => 'device-alt-3', 'variant' => 'A'],
            ['movie_id' => 3, 'device_id' => 'device-alt-4', 'variant' => 'A'],
        ])->map(function (array $row) use ($now): array {
            return [
                'movie_id' => $row['movie_id'],
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
            ['device_id' => 'device-even-2', 'path' => '/', 'placement' => 'home', 'movie_id' => 1],
            ['device_id' => 'device-even-2', 'path' => '/movies/1', 'placement' => 'show', 'movie_id' => 1],
            ['device_id' => 'device-odd', 'path' => '/', 'placement' => 'home', 'movie_id' => 2],
            ['device_id' => 'device-odd', 'path' => '/trends', 'placement' => 'trends', 'movie_id' => 3],
        ];

        DB::table('device_history')->insert(array_map(function (array $row) use ($now): array {
            $entry = [
                'device_id' => $row['device_id'],
                'viewed_at' => $now->subDays(1),
                'created_at' => $now->subDays(1),
                'updated_at' => $now->subDays(1),
            ];

            if (Schema::hasColumn('device_history', 'path')) {
                $entry['path'] = $row['path'];
            }

            if (Schema::hasColumn('device_history', 'page')) {
                $entry['page'] = $row['placement'];
            }

            if (Schema::hasColumn('device_history', 'placement')) {
                $entry['placement'] = $row['placement'];
            }

            if (Schema::hasColumn('device_history', 'movie_id')) {
                $entry['movie_id'] = $row['movie_id'];
            }

            return $entry;
        }, $views));

        $ssrMetrics = [
            ['path' => '/', 'score' => 85, 'delta' => 1, 'first_byte_ms' => 210],
            ['path' => '/trends', 'score' => 78, 'delta' => 1, 'first_byte_ms' => 238],
            ['path' => '/', 'score' => 90, 'delta' => 0, 'first_byte_ms' => 192],
            ['path' => '/trends', 'score' => 72, 'delta' => 0, 'first_byte_ms' => 265],
        ];

        $payloadColumn = $this->resolveColumn(['payload', 'raw_payload']);
        $normalizedColumn = $this->resolveColumn(['normalized_payload', 'payload_normalized']);
        $hasRecordedAt = Schema::hasColumn('ssr_metrics', 'recorded_at');

        DB::table('ssr_metrics')->insert(array_map(function (array $row) use ($now, $payloadColumn, $normalizedColumn, $hasRecordedAt): array {
            $timestamp = $now->subDays($row['delta']);

            $raw = [
                'path' => $row['path'],
                'score' => $row['score'],
                'html_size' => null,
                'meta_count' => null,
                'og_count' => null,
                'ldjson_count' => null,
                'img_count' => null,
                'blocking_scripts' => null,
                'first_byte_ms' => $row['first_byte_ms'],
            ];

            $normalized = SsrMetricPayload::normalize($raw);

            $data = [
                'path' => $row['path'],
                'score' => $normalized['score'],
                'first_byte_ms' => $normalized['first_byte_ms'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if ($hasRecordedAt) {
                $data['recorded_at'] = $timestamp;
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count')) {
                $data['meta_count'] = $normalized['counts']['meta'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count')) {
                $data['og_count'] = $normalized['counts']['open_graph'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                $data['ldjson_count'] = $normalized['counts']['ldjson'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count')) {
                $data['img_count'] = $normalized['counts']['images'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                $data['blocking_scripts'] = $normalized['counts']['blocking_scripts'];
            }

            if ($payloadColumn !== null) {
                $data[$payloadColumn] = json_encode($raw, JSON_THROW_ON_ERROR);
            }

            if ($normalizedColumn !== null) {
                $data[$normalizedColumn] = json_encode($normalized, JSON_THROW_ON_ERROR);
            }

            return $data;
        }, $ssrMetrics));
    }

    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('ssr_metrics', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
