<?php

declare(strict_types=1);

namespace Tests\Seeders;

use App\Models\Movie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoContentSeeder
{
    public function __invoke(): void
    {
        $now = Carbon::now();

        Movie::factory()->count(10)->create();

        $clicks = [
            ['movie_id' => 1, 'variant' => 'A', 'placement' => 'home'],
            ['movie_id' => 1, 'variant' => 'B', 'placement' => 'home'],
            ['movie_id' => 2, 'variant' => 'A', 'placement' => 'home'],
        ];

        DB::table('rec_clicks')->insert(array_map(function (array $row) use ($now): array {
            return [
                'movie_id' => $row['movie_id'],
                'variant' => $row['variant'],
                'placement' => $row['placement'],
                'created_at' => $now->copy()->subDays(1),
                'updated_at' => $now->copy()->subDays(1),
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
            ['path' => '/', 'score' => 85, 'delta' => 1, 'html_size' => 420_000, 'meta' => 26, 'og' => 4, 'ld' => 2, 'img' => 16, 'blocking' => 1, 'first_byte_ms' => 200],
            ['path' => '/trends', 'score' => 78, 'delta' => 1, 'html_size' => 380_000, 'meta' => 24, 'og' => 3, 'ld' => 1, 'img' => 12, 'blocking' => 1, 'first_byte_ms' => 210],
            ['path' => '/', 'score' => 90, 'delta' => 0, 'html_size' => 430_000, 'meta' => 28, 'og' => 4, 'ld' => 2, 'img' => 18, 'blocking' => 0, 'first_byte_ms' => 190],
            ['path' => '/trends', 'score' => 72, 'delta' => 0, 'html_size' => 360_000, 'meta' => 22, 'og' => 3, 'ld' => 1, 'img' => 10, 'blocking' => 2, 'first_byte_ms' => 205],
        ];

        DB::table('ssr_metrics')->insert(array_map(function (array $row) use ($now): array {
            $timestamp = $now->copy()->subDays($row['delta']);

            return [
                'path' => $row['path'],
                'score' => $row['score'],
                'html_size' => $row['html_size'],
                'meta_count' => $row['meta'],
                'og_count' => $row['og'],
                'ldjson_count' => $row['ld'],
                'img_count' => $row['img'],
                'blocking_scripts' => $row['blocking'],
                'first_byte_ms' => $row['first_byte_ms'],
                'has_json_ld' => $row['ld'] > 0,
                'has_open_graph' => $row['og'] >= 3,
                'recorded_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $ssrMetrics));
    }
}
