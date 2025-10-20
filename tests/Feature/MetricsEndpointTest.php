<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_returns_prometheus_payload(): void
    {
        config()->set('cache.default', 'array');
        config()->set('cache.stores.redis', null);

        CarbonImmutable::setTestNow('2025-01-10 00:00:00');

        $movie = Movie::factory()->create();
        $capturedAt = CarbonImmutable::parse('2025-01-09 12:00:00');

        DB::table('rec_ab_logs')->insert([
            [
                'movie_id' => $movie->id,
                'device_id' => 'device-a',
                'placement' => 'home',
                'variant' => 'A',
                'payload' => null,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
            [
                'movie_id' => $movie->id,
                'device_id' => 'device-b',
                'placement' => 'home',
                'variant' => 'B',
                'payload' => null,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
        ]);

        DB::table('rec_clicks')->insert([
            [
                'movie_id' => $movie->id,
                'device_id' => 'device-a',
                'placement' => 'home',
                'variant' => 'A',
                'position' => 1,
                'clicked_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
        ]);

        $metricRows = [
            [
                'path' => '/home',
                'score' => 88,
                'size' => 1024,
                'html_bytes' => 1024,
                'meta_count' => 4,
                'og_count' => 2,
                'ldjson_count' => 1,
                'img_count' => 6,
                'blocking_scripts' => 0,
                'first_byte_ms' => 120,
                'has_json_ld' => true,
                'has_open_graph' => true,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
            [
                'path' => '/movie',
                'score' => 92,
                'size' => 2048,
                'html_bytes' => 2048,
                'meta_count' => 5,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 8,
                'blocking_scripts' => 1,
                'first_byte_ms' => 150,
                'has_json_ld' => true,
                'has_open_graph' => true,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
        ];

        if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
            $metricRows = array_map(function (array $row) use ($capturedAt): array {
                $row['recorded_at'] = $capturedAt;

                return $row;
            }, $metricRows);
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            $metricRows = array_map(function (array $row) use ($capturedAt): array {
                $row['collected_at'] = $capturedAt;

                return $row;
            }, $metricRows);
        }

        DB::table('ssr_metrics')->insert($metricRows);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'importers',
            'payload' => '{}',
            'exception' => 'Example exception',
            'failed_at' => $capturedAt,
        ]);

        $response = $this->get('/metrics');

        $response->assertOk();
        $this->assertStringStartsWith(
            'text/plain; version=0.0.4',
            (string) $response->headers->get('Content-Type'),
        );

        $body = (string) $response->getContent();

        $this->assertStringContainsString('movierec_ctr_impressions_total 2', $body);
        $this->assertStringContainsString('movierec_ctr_clicks_total 1', $body);
        $this->assertStringContainsString('movierec_ctr_rate 0.5', $body);
        $this->assertStringContainsString('movierec_ssr_score_average 90', $body);
        $this->assertStringContainsString('movierec_ssr_first_byte_ms_average 135', $body);
        $this->assertStringContainsString('movierec_ssr_samples_total 2', $body);
        $this->assertStringContainsString('movierec_importer_failures_total 1', $body);
    }
}
