<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
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
        config()->set('ssrmetrics.storage.fallback.disk', 'local');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        Storage::fake('local');

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
            'recorded_at' => $now->toIso8601String(),
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
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class)
        );

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
        config()->set('ssrmetrics.storage.fallback.disk', 'local');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        Storage::fake('local');

        $now = Carbon::parse('2024-01-02 08:30:00');
        Carbon::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');

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
            'recorded_at' => $now->toIso8601String(),
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
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class)
        );

        $fallbackDisk = config('ssrmetrics.storage.fallback.disk');
        $fallbackFile = config('ssrmetrics.storage.fallback.files.incoming');

        Storage::disk($fallbackDisk)->assertExists($fallbackFile);

        $contents = Storage::disk($fallbackDisk)->get($fallbackFile);
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($now->toIso8601String(), $decoded['ts']);
        $this->assertSame($now->toIso8601String(), $decoded['recorded_at']);
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

    public function test_it_writes_to_configured_fallback_disk(): void
    {
        config()->set('ssrmetrics.storage.fallback.disk', 'metrics-disk');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/custom.jsonl');

        Storage::fake('metrics-disk');

        $now = Carbon::parse('2024-02-02 10:00:00');
        Carbon::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');

        $payload = [
            'path' => '/custom',
            'score' => 65,
            'html_size' => 512,
            'meta_count' => 1,
            'og_count' => 0,
            'ldjson_count' => 0,
            'img_count' => 2,
            'blocking_scripts' => 0,
            'first_byte_ms' => 45,
            'collected_at' => $now->toIso8601String(),
            'recorded_at' => $now->toIso8601String(),
            'meta' => [],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class)
        );

        Storage::disk('metrics-disk')->assertExists('metrics/custom.jsonl');

        $content = Storage::disk('metrics-disk')->get('metrics/custom.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $content)));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($now->toIso8601String(), $decoded['ts']);
        $this->assertSame($now->toIso8601String(), $decoded['recorded_at']);
        $this->assertSame('/custom', $decoded['path']);
    }

    public function test_it_prunes_database_rows_based_on_retention(): void
    {
        config()->set('ssrmetrics.storage.fallback.disk', 'local');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        $normalizer = app(SsrMetricPayloadNormalizer::class);
        $recorder = app(SsrMetricRecorder::class);

        $oldTimestamp = Carbon::parse('2024-02-01 00:00:00');

        config()->set('ssrmetrics.retention.primary_days', 0);

        $recorder->record(
            $normalizer->normalize([
                'path' => '/old',
                'score' => 40,
                'html_size' => 256,
                'meta_count' => 1,
                'og_count' => 0,
                'ldjson_count' => 0,
                'img_count' => 1,
                'blocking_scripts' => 0,
                'first_byte_ms' => 20,
                'collected_at' => $oldTimestamp->toIso8601String(),
                'recorded_at' => $oldTimestamp->toIso8601String(),
                'meta' => [],
            ]),
            [
                'path' => '/old',
                'score' => 40,
                'html_size' => 256,
                'meta_count' => 1,
                'og_count' => 0,
                'ldjson_count' => 0,
                'img_count' => 1,
                'blocking_scripts' => 0,
                'first_byte_ms' => 20,
                'collected_at' => $oldTimestamp->toIso8601String(),
                'recorded_at' => $oldTimestamp->toIso8601String(),
                'meta' => [],
            ]
        );

        config()->set('ssrmetrics.retention.primary_days', 5);

        $now = Carbon::parse('2024-02-10 12:00:00');
        Carbon::setTestNow($now);

        $payload = [
            'path' => '/fresh',
            'score' => 90,
            'html_size' => 1024,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 1,
            'img_count' => 2,
            'blocking_scripts' => 0,
            'first_byte_ms' => 80,
            'collected_at' => $now->toIso8601String(),
            'recorded_at' => $now->toIso8601String(),
            'meta' => [],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle($normalizer, $recorder);

        $paths = DB::table('ssr_metrics')->pluck('path')->all();

        $this->assertContains('/fresh', $paths);
        $this->assertNotContains('/old', $paths);
    }

    public function test_it_prunes_fallback_records_based_on_retention(): void
    {
        config()->set('ssrmetrics.storage.fallback.disk', 'metrics-disk');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        Storage::fake('metrics-disk');

        Schema::dropIfExists('ssr_metrics');

        $records = [
            ['ts' => '2024-03-01T00:00:00Z', 'path' => '/stale', 'score' => 10],
            ['ts' => '2024-03-05T12:00:00Z', 'path' => '/recent', 'score' => 80],
        ];

        $lines = array_map(static fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR), $records);
        Storage::disk('metrics-disk')->put('metrics/ssr.jsonl', implode("\n", $lines));

        config()->set('ssrmetrics.retention.fallback_days', 3);

        $now = Carbon::parse('2024-03-08 09:00:00');
        Carbon::setTestNow($now);

        $payload = [
            'path' => '/fresh',
            'score' => 75,
            'html_size' => 700,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 1,
            'img_count' => 1,
            'blocking_scripts' => 0,
            'first_byte_ms' => 60,
            'collected_at' => $now->toIso8601String(),
            'recorded_at' => $now->toIso8601String(),
            'meta' => [],
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class)
        );

        $content = Storage::disk('metrics-disk')->get('metrics/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $content)));

        $this->assertCount(2, $lines);

        $decoded = array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);

        $paths = array_column($decoded, 'path');

        $this->assertSame(['/recent', '/fresh'], $paths);
    }
}
