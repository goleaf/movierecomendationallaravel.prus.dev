<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Support\SsrMetricPayload;
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

        $resolveColumn = static function (array $candidates): ?string {
            foreach ($candidates as $candidate) {
                if (Schema::hasColumn('ssr_metrics', $candidate)) {
                    return $candidate;
                }
            }

            return null;
        };

        $payloadColumn = $resolveColumn(['payload', 'raw_payload']);
        $normalizedColumn = $resolveColumn(['normalized_payload', 'payload_normalized']);
        $hasRecordedAt = Schema::hasColumn('ssr_metrics', 'recorded_at');

        $metricRows = [
            [
                'path' => '/home',
                'score' => 88,
                'html_size' => 1024,
                'meta_count' => 4,
                'og_count' => 2,
                'ldjson_count' => 1,
                'img_count' => 6,
                'blocking_scripts' => 0,
                'first_byte_ms' => 120,
            ],
            [
                'path' => '/movie',
                'score' => 92,
                'html_size' => 2048,
                'meta_count' => 5,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 8,
                'blocking_scripts' => 1,
                'first_byte_ms' => 150,
            ],
        ];

        DB::table('ssr_metrics')->insert(array_map(function (array $row) use ($capturedAt, $payloadColumn, $normalizedColumn, $hasRecordedAt): array {
            $raw = $row + [
                'meta' => [
                    'first_byte_ms' => $row['first_byte_ms'],
                    'html_size' => $row['html_size'],
                    'meta_count' => $row['meta_count'],
                    'og_count' => $row['og_count'],
                    'ldjson_count' => $row['ldjson_count'],
                    'img_count' => $row['img_count'],
                    'blocking_scripts' => $row['blocking_scripts'],
                    'has_json_ld' => $row['ldjson_count'] > 0,
                    'has_open_graph' => $row['og_count'] > 0,
                ],
            ];

            $normalized = SsrMetricPayload::normalize($raw);

            $data = [
                'path' => $row['path'],
                'score' => $normalized['score'],
                'size' => $row['html_size'],
                'meta_count' => $normalized['counts']['meta'],
                'og_count' => $normalized['counts']['open_graph'],
                'ldjson_count' => $normalized['counts']['ldjson'],
                'img_count' => $normalized['counts']['images'],
                'blocking_scripts' => $normalized['counts']['blocking_scripts'],
                'first_byte_ms' => $normalized['first_byte_ms'],
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];

            if ($hasRecordedAt) {
                $data['recorded_at'] = $capturedAt;
            }

            if ($payloadColumn !== null) {
                $data[$payloadColumn] = json_encode($raw, JSON_THROW_ON_ERROR);
            }

            if ($normalizedColumn !== null) {
                $data[$normalizedColumn] = json_encode($normalized, JSON_THROW_ON_ERROR);
            }

            return $data;
        }, $metricRows));

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
