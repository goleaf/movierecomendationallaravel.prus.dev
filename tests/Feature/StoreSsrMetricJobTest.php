<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
use App\Services\Analytics\SsrMetricsService as AnalyticsSsrMetricsService;
use App\Services\SsrMetricPayloadNormalizer;
use App\Services\SsrMetricRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSsrMetricJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_inserts_metric_into_database_when_table_exists(): void
    {
        Storage::fake('local');

        $now = Carbon::parse('2024-01-01 12:00:00');
        Carbon::setTestNow($now);

        $payload = [
            'path' => '/movies/42',
            'movie_id' => 42,
            'score' => 85,
            'html_size' => 2048,
            'meta_count' => 5,
            'og_count' => 3,
            'ldjson_count' => 1,
            'img_count' => 4,
            'blocking_scripts' => 2,
            'first_byte_ms' => 123,
            'recorded_at' => $now->toIso8601String(),
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class),
        );

        $analytics = app(AnalyticsSsrMetricsService::class);
        $headline = $analytics->headline();
        $this->assertSame(85, $headline['score']);
        $this->assertSame(1, $headline['paths']);

        $trend = $analytics->trend(1);
        $this->assertSame([$now->toDateString()], $trend['labels']);
        $this->assertEqualsWithDelta(85.0, $trend['datasets'][0]['data'][0], 0.01);

        $row = DB::table('ssr_metrics')->first();

        $this->assertNotNull($row);
        $this->assertSame('/movies/42', $row->path);
        $this->assertSame(85, (int) $row->score);
        $this->assertSame(123, (int) $row->first_byte_ms);
        $this->assertTrue((bool) $row->has_json_ld);
        $this->assertTrue((bool) $row->has_open_graph);

        if (Schema::hasColumn('ssr_metrics', 'meta')) {
            $meta = json_decode((string) $row->meta, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(42, $meta['movie_id']);
            $this->assertSame($now->toIso8601String(), $meta['recorded_at']);
        }

        if (Schema::hasColumn('ssr_metrics', 'normalized_payload')) {
            $normalized = json_decode((string) $row->normalized_payload, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('/movies/42', $normalized['path']);
            $this->assertSame(42, $normalized['movie_id']);
            $this->assertSame($now->toIso8601String(), $normalized['recorded_at']);
        }

        if (Schema::hasColumn('ssr_metrics', 'movie_id')) {
            $this->assertSame(42, (int) $row->movie_id);
        }
    }

    public function test_it_appends_metric_to_jsonl_when_table_missing(): void
    {
        Storage::fake('local');

        $now = Carbon::parse('2024-01-02 08:30:00');
        Carbon::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');

        $payload = [
            'path' => '/landing',
            'movie_id' => 7,
            'score' => 70,
            'html_size' => 1024,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 0,
            'img_count' => 3,
            'blocking_scripts' => 1,
            'first_byte_ms' => 98,
            'recorded_at' => $now->toIso8601String(),
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class),
        );

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');

        $contents = Storage::disk('local')->get('metrics/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($now->toIso8601String(), $decoded['ts']);
        $this->assertSame($now->toIso8601String(), $decoded['recorded_at']);
        $this->assertSame('/landing', $decoded['path']);
        $this->assertSame(7, $decoded['movie_id']);
        $this->assertSame(70, $decoded['score']);
        $this->assertSame(1024, $decoded['size']);
        $this->assertSame(1024, $decoded['html_size']);
        $this->assertSame(1024, $decoded['html_bytes']);
        $this->assertSame(2, $decoded['meta_count']);
        $this->assertSame(1, $decoded['og']);
        $this->assertSame(1, $decoded['og_count']);
        $this->assertSame(0, $decoded['ld']);
        $this->assertSame(0, $decoded['ldjson_count']);
        $this->assertSame(3, $decoded['imgs']);
        $this->assertSame(3, $decoded['img_count']);
        $this->assertSame(1, $decoded['blocking']);
        $this->assertSame(1, $decoded['blocking_scripts']);
        $this->assertSame(98, $decoded['first_byte_ms']);
        $this->assertFalse($decoded['has_json_ld']);
        $this->assertTrue($decoded['has_open_graph']);

        $this->assertIsArray($decoded['normalized']);
        $this->assertSame('/landing', $decoded['normalized']['path']);
        $this->assertSame(7, $decoded['normalized']['movie_id']);
        $this->assertSame($now->toIso8601String(), $decoded['normalized']['recorded_at']);

        $this->assertIsArray($decoded['original']);
        $this->assertSame($payload['path'], $decoded['original']['path']);
        $this->assertSame($payload['movie_id'], $decoded['original']['movie_id']);

        $analytics = app(AnalyticsSsrMetricsService::class);
        $headline = $analytics->headline();
        $this->assertSame(70, $headline['score']);
        $this->assertSame(1, $headline['paths']);

        $trend = $analytics->trend(1);
        $this->assertSame([$now->toDateString()], $trend['labels']);
        $this->assertEqualsWithDelta(70.0, $trend['datasets'][0]['data'][0], 0.01);
    }
}
