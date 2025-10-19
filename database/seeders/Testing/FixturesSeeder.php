<?php

declare(strict_types=1);

namespace Database\Seeders\Testing;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FixturesSeeder extends Seeder
{
    public function run(): void
    {
        Cache::flush();

        $now = Carbon::now();
        $daysAgo = static fn (int $days): Carbon => $now->copy()->subDays($days);

        $matrix = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_date' => Carbon::createFromDate(1999, 3, 31),
        ]);

        $timeTravelers = Movie::factory()->create([
            'title' => 'Time Travelers',
            'release_date' => Carbon::createFromDate(2023, 7, 21),
        ]);

        $impressions = [
            ['movie' => $matrix, 'variant' => 'A', 'placement' => 'home', 'days' => 3, 'device' => 'device-even-1'],
            ['movie' => $matrix, 'variant' => 'B', 'placement' => 'home', 'days' => 2, 'device' => 'device-odd'],
            ['movie' => $matrix, 'variant' => 'A', 'placement' => 'show', 'days' => 1, 'device' => 'device-even-3'],
            ['movie' => $timeTravelers, 'variant' => 'A', 'placement' => 'home', 'days' => 3, 'device' => 'device-even-4'],
            ['movie' => $timeTravelers, 'variant' => 'B', 'placement' => 'home', 'days' => 2, 'device' => 'device-odd-2'],
            ['movie' => $timeTravelers, 'variant' => 'A', 'placement' => 'show', 'days' => 3, 'device' => 'device-even-5'],
        ];

        DB::table('rec_ab_logs')->insert(array_map(static function (array $row) use ($daysAgo): array {
            $ts = $daysAgo($row['days']);

            return [
                'movie_id' => $row['movie']->id,
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'device_id' => $row['device'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }, $impressions));

        $clicks = [
            ['movie' => $matrix, 'variant' => 'A', 'placement' => 'home', 'days' => 3],
            ['movie' => $matrix, 'variant' => 'B', 'placement' => 'home', 'days' => 2],
            ['movie' => $matrix, 'variant' => 'A', 'placement' => 'show', 'days' => 1],
            ['movie' => $timeTravelers, 'variant' => 'A', 'placement' => 'home', 'days' => 3],
            ['movie' => $timeTravelers, 'variant' => 'B', 'placement' => 'home', 'days' => 2],
            ['movie' => $timeTravelers, 'variant' => 'A', 'placement' => 'show', 'days' => 3],
        ];

        DB::table('rec_clicks')->insert(array_map(static function (array $row) use ($daysAgo): array {
            $ts = $daysAgo($row['days']);

            return [
                'movie_id' => $row['movie']->id,
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }, $clicks));

        $views = [];
        for ($i = 0; $i < 12; $i++) {
            $ts = $now->copy()->subHours($i + 1);
            $views[] = [
                'device_id' => 'viewer-'.$i,
                'path' => $i % 2 === 0 ? '/' : '/trends',
                'viewed_at' => $ts,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }

        DB::table('device_history')->insert($views);

        $metrics = [
            [
                'path' => '/',
                'score' => 96,
                'days' => 1,
                'html_size' => 512_000,
                'meta_count' => 28,
                'og_count' => 4,
                'ldjson_count' => 2,
                'img_count' => 18,
                'blocking_scripts' => 1,
                'first_byte_ms' => 180,
            ],
            [
                'path' => '/',
                'score' => 88,
                'days' => 0,
                'html_size' => 640_000,
                'meta_count' => 24,
                'og_count' => 3,
                'ldjson_count' => 2,
                'img_count' => 22,
                'blocking_scripts' => 3,
                'first_byte_ms' => 220,
            ],
            [
                'path' => '/trends',
                'score' => 90,
                'days' => 1,
                'html_size' => 420_000,
                'meta_count' => 20,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 14,
                'blocking_scripts' => 1,
                'first_byte_ms' => 190,
            ],
            [
                'path' => '/trends',
                'score' => 92,
                'days' => 0,
                'html_size' => 380_000,
                'meta_count' => 22,
                'og_count' => 3,
                'ldjson_count' => 2,
                'img_count' => 12,
                'blocking_scripts' => 0,
                'first_byte_ms' => 170,
            ],
            [
                'path' => '/movies/'.$timeTravelers->id,
                'score' => 94,
                'days' => 0,
                'html_size' => 450_000,
                'meta_count' => 26,
                'og_count' => 4,
                'ldjson_count' => 2,
                'img_count' => 16,
                'blocking_scripts' => 1,
                'first_byte_ms' => 185,
            ],
        ];

        DB::table('ssr_metrics')->insert(array_map(static function (array $row) use ($daysAgo): array {
            $ts = $daysAgo($row['days']);

            return [
                'path' => $row['path'],
                'score' => $row['score'],
                'html_size' => $row['html_size'],
                'meta_count' => $row['meta_count'],
                'og_count' => $row['og_count'],
                'ldjson_count' => $row['ldjson_count'],
                'img_count' => $row['img_count'],
                'blocking_scripts' => $row['blocking_scripts'],
                'first_byte_ms' => $row['first_byte_ms'],
                'has_json_ld' => $row['ldjson_count'] > 0,
                'has_open_graph' => $row['og_count'] >= 3,
                'recorded_at' => $ts,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }, $metrics));
    }
}
