<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_exports_prometheus_payload(): void
    {
        $movie = Movie::factory()->create();
        $now = now();

        $impressions = [];
        for ($i = 0; $i < 10; $i++) {
            $impressions[] = [
                'movie_id' => $movie->id,
                'device_id' => 'device-home-'.$i,
                'variant' => 'A',
                'placement' => 'home',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        for ($i = 0; $i < 5; $i++) {
            $impressions[] = [
                'movie_id' => $movie->id,
                'device_id' => 'device-trends-'.$i,
                'variant' => 'B',
                'placement' => 'trends',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('rec_ab_logs')->insert($impressions);

        $clicks = [
            [
                'movie_id' => $movie->id,
                'variant' => 'A',
                'placement' => 'home',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'movie_id' => $movie->id,
                'variant' => 'A',
                'placement' => 'home',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'movie_id' => $movie->id,
                'variant' => 'B',
                'placement' => 'trends',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if (Schema::hasColumn('rec_clicks', 'device_id')) {
            foreach ($clicks as $index => $click) {
                $clicks[$index]['device_id'] = 'device-click-'.($index + 1);
            }
        }

        DB::table('rec_clicks')->insert($clicks);

        DB::table('ssr_metrics')->insert([
            [
                'path' => '/',
                'score' => 90,
                'ttfb_ms' => 100,
                'size' => 500_000,
                'meta_count' => 20,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 10,
                'blocking_scripts' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'path' => '/',
                'score' => 88,
                'ttfb_ms' => 200,
                'size' => 480_000,
                'meta_count' => 22,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 12,
                'blocking_scripts' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'path' => '/trends',
                'score' => 85,
                'ttfb_ms' => 300,
                'size' => 470_000,
                'meta_count' => 21,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 11,
                'blocking_scripts' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'path' => '/trends',
                'score' => 87,
                'ttfb_ms' => 400,
                'size' => 460_000,
                'meta_count' => 23,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 13,
                'blocking_scripts' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'imports-default',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\ImportMovies',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => 'App\\Jobs\\ImportMovies',
                ],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Test importer failure',
            'failed_at' => $now,
        ]);

        $response = $this->get('/metrics');

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertIsString($contentType);
        $this->assertStringStartsWith('text/plain; version=0.0.4', $contentType);

        $content = $response->getContent();
        $this->assertNotNull($content);

        $this->assertStringContainsString('movierec_ctr_per_placement{placement="home"} 0.200000', $content);
        $this->assertStringContainsString('movierec_ctr_per_placement{placement="trends"} 0.200000', $content);
        $this->assertStringContainsString('movierec_ssr_ttfb_p95ms 300.00', $content);
        $this->assertStringContainsString('movierec_importer_errors_total 1', $content);
    }
}
