<?php

declare(strict_types=1);

namespace Database\Seeders\Testing;

use App\Models\Movie;
use App\Support\SsrMetricPayload;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixturesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $daysAgo = static fn (int $days) => $now->copy()->subDays($days);

        $movies = collect([
            'Time Travelers' => [
                'imdb_tt' => 'tt9000001',
                'plot' => 'Temporal rescue thriller set across collapsing timelines.',
                'type' => 'movie',
                'year' => 2024,
                'release_date' => '2024-02-16',
                'imdb_rating' => 7.9,
                'imdb_votes' => 40000,
                'runtime_min' => 128,
                'genres' => ['Sci-Fi', 'Adventure'],
                'poster_url' => 'https://images.test/time-travelers.jpg',
            ],
            'Indie Darling' => [
                'imdb_tt' => 'tt9000002',
                'plot' => 'A heartfelt drama about rebuilding a coastal town.',
                'type' => 'movie',
                'year' => 2023,
                'release_date' => '2023-09-05',
                'imdb_rating' => 8.7,
                'imdb_votes' => 8000,
                'runtime_min' => 112,
                'genres' => ['Drama'],
                'poster_url' => 'https://images.test/indie-darling.jpg',
            ],
            'Space Odyssey' => [
                'imdb_tt' => 'tt9000003',
                'plot' => 'Veteran astronauts chart a perilous shortcut through dark matter.',
                'type' => 'movie',
                'year' => 2020,
                'release_date' => '2020-07-16',
                'imdb_rating' => 8.5,
                'imdb_votes' => 150000,
                'runtime_min' => 154,
                'genres' => ['Sci-Fi'],
                'poster_url' => 'https://images.test/space-odyssey.jpg',
            ],
            'Neon City' => [
                'imdb_tt' => 'tt9000004',
                'plot' => 'Cyberpunk mystery unraveling a megacorp conspiracy.',
                'type' => 'movie',
                'year' => 2022,
                'release_date' => '2022-04-21',
                'imdb_rating' => 8.1,
                'imdb_votes' => 60000,
                'runtime_min' => 118,
                'genres' => ['Sci-Fi', 'Thriller'],
                'poster_url' => 'https://images.test/neon-city.jpg',
            ],
            'Documentary Archive' => [
                'imdb_tt' => 'tt9000005',
                'plot' => 'Restoration experts digitise forgotten newsreels.',
                'type' => 'movie',
                'year' => 2018,
                'release_date' => '2018-03-11',
                'imdb_rating' => 7.2,
                'imdb_votes' => 5000,
                'runtime_min' => 94,
                'genres' => ['Documentary'],
                'poster_url' => 'https://images.test/documentary-archive.jpg',
            ],
        ])->map(function (array $attributes, string $title) use ($now): Movie {
            return Movie::query()->create([
                'title' => $title,
                ...$attributes,
                'translations' => [
                    'title' => [
                        'ru' => $title,
                    ],
                    'plot' => [
                        'ru' => $attributes['plot'],
                    ],
                ],
                'raw' => ['source' => 'testing'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        /** @var Movie $timeTravelers */
        $timeTravelers = $movies['Time Travelers'];
        /** @var Movie $indie */
        $indie = $movies['Indie Darling'];
        /** @var Movie $space */
        $space = $movies['Space Odyssey'];
        /** @var Movie $neon */
        $neon = $movies['Neon City'];

        $impressions = [
            ['movie' => $timeTravelers, 'device' => 'dev-a-1', 'variant' => 'A', 'placement' => 'home', 'days' => 1],
            ['movie' => $timeTravelers, 'device' => 'dev-a-2', 'variant' => 'A', 'placement' => 'home', 'days' => 2],
            ['movie' => $timeTravelers, 'device' => 'dev-a-3', 'variant' => 'A', 'placement' => 'show', 'days' => 2],
            ['movie' => $indie, 'device' => 'dev-a-4', 'variant' => 'A', 'placement' => 'trends', 'days' => 3],
            ['movie' => $indie, 'device' => 'dev-a-5', 'variant' => 'A', 'placement' => 'home', 'days' => 1],
            ['movie' => $indie, 'device' => 'dev-a-6', 'variant' => 'A', 'placement' => 'show', 'days' => 1],
            ['movie' => $space, 'device' => 'dev-a-7', 'variant' => 'A', 'placement' => 'home', 'days' => 0],
            ['movie' => $space, 'device' => 'dev-a-8', 'variant' => 'A', 'placement' => 'trends', 'days' => 0],
            ['movie' => $neon, 'device' => 'dev-a-9', 'variant' => 'A', 'placement' => 'home', 'days' => 4],
            ['movie' => $timeTravelers, 'device' => 'dev-b-1', 'variant' => 'B', 'placement' => 'home', 'days' => 1],
            ['movie' => $indie, 'device' => 'dev-b-2', 'variant' => 'B', 'placement' => 'home', 'days' => 1],
            ['movie' => $space, 'device' => 'dev-b-3', 'variant' => 'B', 'placement' => 'show', 'days' => 2],
            ['movie' => $neon, 'device' => 'dev-b-4', 'variant' => 'B', 'placement' => 'show', 'days' => 0],
            ['movie' => $timeTravelers, 'device' => 'dev-b-5', 'variant' => 'B', 'placement' => 'trends', 'days' => 3],
            ['movie' => $indie, 'device' => 'dev-b-6', 'variant' => 'B', 'placement' => 'trends', 'days' => 1],
            ['movie' => $space, 'device' => 'dev-b-7', 'variant' => 'B', 'placement' => 'home', 'days' => 5],
            ['movie' => $neon, 'device' => 'dev-b-8', 'variant' => 'B', 'placement' => 'trends', 'days' => 4],
        ];

        DB::table('rec_ab_logs')->insert(array_map(static function (array $row) use ($daysAgo): array {
            $ts = $daysAgo($row['days']);

            return [
                'movie_id' => $row['movie']->id,
                'device_id' => $row['device'],
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }, $impressions));

        $clicks = [
            ['movie' => $timeTravelers, 'device' => 'dev-click-1', 'variant' => 'A', 'placement' => 'home', 'days' => 1],
            ['movie' => $timeTravelers, 'device' => 'dev-click-2', 'variant' => 'A', 'placement' => 'home', 'days' => 2],
            ['movie' => $timeTravelers, 'device' => 'dev-click-3', 'variant' => 'B', 'placement' => 'home', 'days' => 1],
            ['movie' => $timeTravelers, 'device' => 'dev-click-4', 'variant' => 'B', 'placement' => 'home', 'days' => 0],
            ['movie' => $timeTravelers, 'device' => 'dev-click-5', 'variant' => 'A', 'placement' => 'trends', 'days' => 0],
            ['movie' => $indie, 'device' => 'dev-click-6', 'variant' => 'A', 'placement' => 'show', 'days' => 1],
            ['movie' => $indie, 'device' => 'dev-click-7', 'variant' => 'B', 'placement' => 'show', 'days' => 0],
            ['movie' => $indie, 'device' => 'dev-click-8', 'variant' => 'A', 'placement' => 'trends', 'days' => 0],
            ['movie' => $space, 'device' => 'dev-click-9', 'variant' => 'A', 'placement' => 'trends', 'days' => 1],
            ['movie' => $space, 'device' => 'dev-click-10', 'variant' => 'B', 'placement' => 'trends', 'days' => 2],
            ['movie' => $neon, 'device' => 'dev-click-11', 'variant' => 'A', 'placement' => 'show', 'days' => 3],
        ];

        DB::table('rec_clicks')->insert(array_map(static function (array $row) use ($daysAgo): array {
            $ts = $daysAgo($row['days']);

            return [
                'movie_id' => $row['movie']->id,
                'device_id' => $row['device'],
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }, $clicks));

        $hasDevicePlacement = Schema::hasColumn('device_history', 'placement');
        $hasDevicePath = Schema::hasColumn('device_history', 'path');
        $hasDevicePage = Schema::hasColumn('device_history', 'page');
        $hasDeviceMovie = Schema::hasColumn('device_history', 'movie_id');

        $viewMovies = [$timeTravelers, $indie, $space, $neon];
        $views = [];

        for ($i = 0; $i < 12; $i++) {
            $ts = $now->copy()->subHours($i + 1);
            $path = $i % 2 === 0 ? '/' : '/trends';
            $placement = $path === '/' ? 'home' : 'trends';

            $entry = [
                'device_id' => 'viewer-'.$i,
                'viewed_at' => $ts,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];

            if ($hasDevicePath) {
                $entry['path'] = $path;
            }

            if ($hasDevicePage) {
                $entry['page'] = $placement;
            }

            if ($hasDevicePlacement) {
                $entry['placement'] = $placement;
            }

            if ($hasDeviceMovie) {
                $entry['movie_id'] = $viewMovies[$i % count($viewMovies)]->id;
            }

            $views[] = $entry;
        }

        DB::table('device_history')->insert($views);

        $metrics = [
            ['path' => '/', 'score' => 96, 'days' => 1, 'size' => 512000, 'meta' => 28, 'og' => 4, 'ld' => 2, 'img' => 18, 'blocking' => 1, 'first_byte_ms' => 185],
            ['path' => '/', 'score' => 88, 'days' => 0, 'size' => 640000, 'meta' => 24, 'og' => 3, 'ld' => 2, 'img' => 22, 'blocking' => 3, 'first_byte_ms' => 244],
            ['path' => '/trends', 'score' => 90, 'days' => 1, 'size' => 420000, 'meta' => 20, 'og' => 3, 'ld' => 1, 'img' => 14, 'blocking' => 1, 'first_byte_ms' => 201],
            ['path' => '/trends', 'score' => 92, 'days' => 0, 'size' => 380000, 'meta' => 22, 'og' => 3, 'ld' => 2, 'img' => 12, 'blocking' => 0, 'first_byte_ms' => 176],
            ['path' => '/movies/'.$timeTravelers->id, 'score' => 94, 'days' => 0, 'size' => 450000, 'meta' => 26, 'og' => 4, 'ld' => 2, 'img' => 16, 'blocking' => 1, 'first_byte_ms' => 192],
        ];

        $payloadColumn = $this->resolveColumn(['payload', 'raw_payload']);
        $normalizedColumn = $this->resolveColumn(['normalized_payload', 'payload_normalized']);
        $hasRecordedAt = Schema::hasColumn('ssr_metrics', 'recorded_at');

        DB::table('ssr_metrics')->insert(array_map(function (array $row) use ($daysAgo, $payloadColumn, $normalizedColumn, $hasRecordedAt): array {
            $ts = $daysAgo($row['days']);

            $raw = [
                'path' => $row['path'],
                'score' => $row['score'],
                'html_size' => $row['size'],
                'meta_count' => $row['meta'],
                'og_count' => $row['og'],
                'ldjson_count' => $row['ld'],
                'img_count' => $row['img'],
                'blocking_scripts' => $row['blocking'],
                'first_byte_ms' => $row['first_byte_ms'],
                'meta' => [
                    'first_byte_ms' => $row['first_byte_ms'],
                    'html_size' => $row['size'],
                    'meta_count' => $row['meta'],
                    'og_count' => $row['og'],
                    'ldjson_count' => $row['ld'],
                    'img_count' => $row['img'],
                    'blocking_scripts' => $row['blocking'],
                    'has_json_ld' => $row['ld'] > 0,
                    'has_open_graph' => $row['og'] > 0,
                ],
            ];

            $normalized = SsrMetricPayload::normalize($raw);

            $data = [
                'path' => $row['path'],
                'score' => $normalized['score'],
                'size' => $raw['html_size'],
                'meta_count' => $normalized['counts']['meta'],
                'og_count' => $normalized['counts']['open_graph'],
                'ldjson_count' => $normalized['counts']['ldjson'],
                'img_count' => $normalized['counts']['images'],
                'blocking_scripts' => $normalized['counts']['blocking_scripts'],
                'first_byte_ms' => $normalized['first_byte_ms'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];

            if ($hasRecordedAt) {
                $data['recorded_at'] = $ts;
            }

            if ($payloadColumn !== null) {
                $data[$payloadColumn] = json_encode($raw, JSON_THROW_ON_ERROR);
            }

            if ($normalizedColumn !== null) {
                $data[$normalizedColumn] = json_encode($normalized, JSON_THROW_ON_ERROR);
            }

            if (Schema::hasColumn('ssr_metrics', 'meta')) {
                $data['meta'] = json_encode($raw['meta'], JSON_THROW_ON_ERROR);
            }

            return $data;
        }, $metrics));
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
