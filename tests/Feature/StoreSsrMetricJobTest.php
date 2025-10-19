<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
use App\Services\SsrMetricsService;
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
        $this->fakeSsrMetricsDisk();
        config()->set('ssrmetrics.storage.fallback.disk', 'ssrmetrics-test');

        $now = Carbon::parse('2024-01-01 12:00:00');
        Carbon::setTestNow($now);

        $payload = [
            'path' => '/movies',
            'score' => 85,
            'html_size' => 2048,
            'html_bytes' => 2048,
            'meta_count' => 5,
            'og_count' => 3,
            'ldjson_count' => 1,
            'img_count' => 4,
            'blocking_scripts' => 2,
            'first_byte_ms' => 123,
            'collected_at' => $now->toIso8601String(),
            'meta' => [
                'first_byte_ms' => 123,
                'html_size' => 2048,
                'html_bytes' => 2048,
                'meta_count' => 5,
                'og_count' => 3,
                'ldjson_count' => 1,
                'img_count' => 4,
                'blocking_scripts' => 2,
                'has_json_ld' => true,
                'has_open_graph' => true,
            ],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(app(SsrMetricsService::class));

        $row = DB::table('ssr_metrics')->first();

        $this->assertNotNull($row);
        $this->assertSame('/movies', $row->path);
        $this->assertSame(85, (int) $row->score);
        $this->assertSame(2048, (int) $row->size);
        $this->assertSame(2048, (int) $row->html_bytes);
        $this->assertSame(5, (int) $row->meta_count);
        $this->assertSame(3, (int) $row->og_count);
        $this->assertSame(1, (int) $row->ldjson_count);
        $this->assertSame(4, (int) $row->img_count);
        $this->assertSame(2, (int) $row->blocking_scripts);
        $this->assertSame(123, (int) $row->first_byte_ms);
        $this->assertTrue((bool) $row->has_json_ld);
        $this->assertTrue((bool) $row->has_open_graph);
        $this->assertSame($now->toDateTimeString(), Carbon::parse($row->created_at)->toDateTimeString());
        $this->assertSame($now->toDateTimeString(), Carbon::parse($row->collected_at)->toDateTimeString());

        if (Schema::hasColumn('ssr_metrics', 'meta')) {
            $meta = json_decode((string) $row->meta, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(2048, $meta['html_bytes']);
            $this->assertTrue($meta['has_json_ld']);
            $this->assertTrue($meta['has_open_graph']);
        }
    }

    public function test_it_appends_metric_to_jsonl_when_table_missing(): void
    {
        $this->fakeSsrMetricsDisk();

        $now = Carbon::parse('2024-01-02 08:30:00');
        Carbon::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');

        config()->set('ssrmetrics.storage.fallback.disk', 'ssrmetrics-test');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'custom/ssr.jsonl');

        $payload = [
            'path' => '/movies',
            'score' => 70,
            'html_size' => 1024,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 0,
            'img_count' => 3,
            'blocking_scripts' => 1,
            'first_byte_ms' => 98,
            'collected_at' => $now->toIso8601String(),
            'meta' => [
                'first_byte_ms' => 98,
                'html_size' => 1024,
                'meta_count' => 2,
                'og_count' => 1,
                'ldjson_count' => 0,
                'img_count' => 3,
                'blocking_scripts' => 1,
                'has_json_ld' => false,
                'has_open_graph' => true,
            ],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(app(SsrMetricsService::class));

        Storage::disk('ssrmetrics-test')->assertExists('custom/ssr.jsonl');

        $contents = Storage::disk('ssrmetrics-test')->get('custom/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($now->toIso8601String(), $decoded['ts']);
        $this->assertSame('/movies', $decoded['path']);
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
    }

    public function test_it_prunes_database_records_based_on_retention_configuration(): void
    {
        $this->fakeSsrMetricsDisk();

        config()->set('ssrmetrics.storage.fallback.disk', 'ssrmetrics-test');
        config()->set('ssrmetrics.retention.primary_days', 2);

        $service = app(SsrMetricsService::class);

        $oldTimestamp = Carbon::parse('2024-03-01 08:00:00');

        $seed = [
            'path' => '/expired',
            'score' => 10,
            'size' => 500,
            'meta_count' => 1,
            'og_count' => 0,
            'ldjson_count' => 0,
            'img_count' => 0,
            'blocking_scripts' => 0,
            'first_byte_ms' => 0,
            'html_bytes' => 500,
            'has_json_ld' => false,
            'has_open_graph' => false,
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
            'collected_at' => $oldTimestamp,
        ];

        if (Schema::hasColumn('ssr_metrics', 'meta')) {
            $seed['meta'] = json_encode(['seed' => true], JSON_THROW_ON_ERROR);
        }

        DB::table('ssr_metrics')->insert($seed);

        $recent = Carbon::parse('2024-03-04 09:15:00');
        Carbon::setTestNow($recent);

        $payload = [
            'path' => '/fresh',
            'score' => 90,
            'html_size' => 1024,
            'html_bytes' => 1024,
            'meta_count' => 3,
            'og_count' => 2,
            'ldjson_count' => 1,
            'img_count' => 2,
            'blocking_scripts' => 1,
            'first_byte_ms' => 120,
            'collected_at' => $recent->toIso8601String(),
            'meta' => [
                'first_byte_ms' => 120,
                'html_size' => 1024,
                'meta_count' => 3,
            ],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle($service);

        $paths = DB::table('ssr_metrics')->pluck('path')->all();

        $this->assertSame(['/fresh'], $paths);
    }

    public function test_it_prunes_fallback_jsonl_records_based_on_retention_configuration(): void
    {
        $this->fakeSsrMetricsDisk();

        config()->set('ssrmetrics.storage.fallback.disk', 'ssrmetrics-test');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');
        config()->set('ssrmetrics.retention.fallback_days', 1);

        Schema::dropIfExists('ssr_metrics');

        $service = app(SsrMetricsService::class);

        $now = Carbon::parse('2024-04-10 12:00:00');
        Carbon::setTestNow($now);

        $oldPayload = [
            'ts' => $now->copy()->subDays(3)->toIso8601String(),
            'path' => '/stale',
            'score' => 40,
        ];

        Storage::disk('ssrmetrics-test')->put('metrics/ssr.jsonl', json_encode($oldPayload, JSON_THROW_ON_ERROR).PHP_EOL);

        $payload = [
            'path' => '/fallback',
            'score' => 75,
            'html_size' => 2048,
            'html_bytes' => 2048,
            'meta_count' => 4,
            'og_count' => 2,
            'ldjson_count' => 1,
            'img_count' => 2,
            'blocking_scripts' => 0,
            'first_byte_ms' => 95,
            'collected_at' => $now->toIso8601String(),
            'meta' => [
                'first_byte_ms' => 95,
                'html_size' => 2048,
                'meta_count' => 4,
            ],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle($service);

        Storage::disk('ssrmetrics-test')->assertExists('metrics/ssr.jsonl');

        $lines = array_values(array_filter(explode(PHP_EOL, Storage::disk('ssrmetrics-test')->get('metrics/ssr.jsonl'))));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/fallback', $decoded['path']);
        $this->assertSame(75, $decoded['score']);
    }

    private function fakeSsrMetricsDisk(): void
    {
        config()->set('filesystems.disks.ssrmetrics-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/ssrmetrics-test'),
            'throw' => false,
            'report' => false,
        ]);

        Storage::fake('ssrmetrics-test');
    }
}
