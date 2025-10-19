<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MetricsCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Csp\AddCspHeaders;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_exposes_prometheus_payload(): void
    {
        $now = CarbonImmutable::create(2024, 5, 1, 12, 0, 0);
        CarbonImmutable::setTestNow($now);

        try {
            config(['cache.default' => 'array']);
            config(['database.redis.client' => 'predis']);
            $this->withoutMiddleware(AddCspHeaders::class);
            $this->seedMetricsData($now);
            app(MetricsCache::class)->flush();

            $response = $this->get('/metrics');

            $response->assertOk();
            $this->assertStringContainsString('text/plain; version=0.0.4', (string) $response->headers->get('Content-Type'));

            $body = $response->getContent();
            $this->assertIsString($body);

            $this->assertStringContainsString('app_ctr_impressions_total{variant="A"} 3', $body);
            $this->assertStringContainsString('app_ctr_impressions_total{variant="B"} 1', $body);
            $this->assertStringContainsString('app_ctr_impressions_total{variant="total"} 4', $body);

            $this->assertStringContainsString('app_ctr_clicks_total{variant="A"} 2', $body);
            $this->assertStringContainsString('app_ctr_clicks_total{variant="total"} 2', $body);
            $this->assertStringContainsString('app_ctr_ctr_percentage{variant="A"} 66.67', $body);

            $this->assertStringContainsString('app_ssr_ttfb_average_milliseconds 150.00', $body);
            $this->assertStringContainsString('app_ssr_ttfb_min_milliseconds 120.00', $body);
            $this->assertStringContainsString('app_ssr_ttfb_max_milliseconds 180.00', $body);
            $this->assertStringContainsString('app_ssr_ttfb_samples_total 2', $body);

            $this->assertStringContainsString('app_importer_failed_jobs_total 1', $body);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function seedMetricsData(CarbonImmutable $now): void
    {
        $movieId = DB::table('movies')->insertGetId([
            'imdb_tt' => 'tt-metrics',
            'title' => 'Metrics Fixture',
            'type' => 'movie',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('rec_ab_logs')->insert([
            [
                'movie_id' => $movieId,
                'device_id' => 'device-1',
                'placement' => 'home',
                'variant' => 'A',
                'created_at' => $now->subDays(1),
                'updated_at' => $now->subDays(1),
            ],
            [
                'movie_id' => $movieId,
                'device_id' => 'device-1',
                'placement' => 'home',
                'variant' => 'A',
                'created_at' => $now->subHours(10),
                'updated_at' => $now->subHours(10),
            ],
            [
                'movie_id' => $movieId,
                'device_id' => 'device-1',
                'placement' => 'trends',
                'variant' => 'A',
                'created_at' => $now->subHours(2),
                'updated_at' => $now->subHours(2),
            ],
            [
                'movie_id' => $movieId,
                'device_id' => 'device-2',
                'placement' => 'home',
                'variant' => 'B',
                'created_at' => $now->subHours(3),
                'updated_at' => $now->subHours(3),
            ],
        ]);

        DB::table('rec_clicks')->insert([
            [
                'movie_id' => $movieId,
                'placement' => 'home',
                'variant' => 'A',
                'created_at' => $now->subHours(10),
                'updated_at' => $now->subHours(10),
            ],
            [
                'movie_id' => $movieId,
                'placement' => 'trends',
                'variant' => 'A',
                'created_at' => $now->subHours(2),
                'updated_at' => $now->subHours(2),
            ],
        ]);

        if (! Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('first_byte_ms')->nullable();
            });
        }

        DB::table('ssr_metrics')->insert([
            [
                'path' => '/',
                'score' => 95,
                'size' => 512000,
                'meta_count' => 10,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 12,
                'blocking_scripts' => 2,
                'first_byte_ms' => 120,
                'created_at' => $now->subMinutes(30),
                'updated_at' => $now->subMinutes(30),
            ],
            [
                'path' => '/trends',
                'score' => 90,
                'size' => 498000,
                'meta_count' => 8,
                'og_count' => 2,
                'ldjson_count' => 1,
                'img_count' => 10,
                'blocking_scripts' => 1,
                'first_byte_ms' => 180,
                'created_at' => $now->subMinutes(15),
                'updated_at' => $now->subMinutes(15),
            ],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'importers',
            'payload' => '{}',
            'exception' => 'Example failure',
            'failed_at' => $now,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Ignored failure',
            'failed_at' => $now,
        ]);
    }
}
