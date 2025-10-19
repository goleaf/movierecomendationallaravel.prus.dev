<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        DB::table('ssr_metrics')->insert([
            [
                'path' => '/home',
                'score' => 88,
                'payload' => json_encode([
                    'html_size' => 1024,
                    'counts' => [
                        'meta' => 4,
                        'og' => 2,
                        'ldjson' => 1,
                        'img' => 6,
                        'blocking_scripts' => 0,
                    ],
                    'first_byte_ms' => 120,
                ], JSON_THROW_ON_ERROR),
                'recorded_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
            [
                'path' => '/movie',
                'score' => 92,
                'payload' => json_encode([
                    'html_size' => 2048,
                    'counts' => [
                        'meta' => 5,
                        'og' => 3,
                        'ldjson' => 1,
                        'img' => 8,
                        'blocking_scripts' => 1,
                    ],
                    'first_byte_ms' => 150,
                ], JSON_THROW_ON_ERROR),
                'recorded_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ],
        ]);

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
