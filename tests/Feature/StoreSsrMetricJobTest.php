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
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ssr-metrics')]
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

        config()->set('ssrmetrics.storage.order', ['database', 'jsonl']);
        config()->set('ssrmetrics.storage.database.enabled', true);
        config()->set('ssrmetrics.storage.database.retention_days', 30);
        config()->set('ssrmetrics.storage.jsonl.disk', 'local');

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

        Storage::disk('local')->assertMissing('metrics/ssr.jsonl');
    }

    public function test_it_appends_metric_to_jsonl_when_table_missing(): void
    {
        Storage::fake('local');

        config()->set('ssrmetrics.storage.order', ['database', 'jsonl']);
        config()->set('ssrmetrics.storage.database.enabled', true);
        config()->set('ssrmetrics.storage.jsonl.enabled', true);
        config()->set('ssrmetrics.storage.jsonl.disk', 'local');
        config()->set('ssrmetrics.storage.jsonl.path', 'metrics/ssr.jsonl');
        config()->set('ssrmetrics.storage.jsonl.retention_days', 14);

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

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');

        $contents = Storage::disk('local')->get('metrics/ssr.jsonl');
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

    public function test_it_prunes_old_database_metrics_when_retention_configured(): void
    {
        Storage::fake('local');

        config()->set('ssrmetrics.storage.order', ['database']);
        config()->set('ssrmetrics.storage.database.enabled', true);
        config()->set('ssrmetrics.storage.database.retention_days', 2);
        config()->set('ssrmetrics.storage.jsonl.enabled', false);

        $now = Carbon::parse('2024-01-10 12:00:00');
        Carbon::setTestNow($now);

        DB::table('ssr_metrics')->insert([
            'path' => '/stale',
            'score' => 50,
            'size' => 100,
            'meta_count' => 1,
            'og_count' => 0,
            'ldjson_count' => 0,
            'img_count' => 0,
            'blocking_scripts' => 0,
            'first_byte_ms' => 10,
            'created_at' => $now->copy()->subDays(5),
            'updated_at' => $now->copy()->subDays(5),
            'collected_at' => $now->copy()->subDays(5),
        ]);

        $payload = [
            'path' => '/fresh',
            'score' => 90,
            'html_size' => 1200,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 1,
            'img_count' => 2,
            'blocking_scripts' => 0,
            'first_byte_ms' => 50,
            'collected_at' => $now->toIso8601String(),
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(app(SsrMetricsService::class));

        $remainingPaths = DB::table('ssr_metrics')->pluck('path')->all();

        $this->assertContains('/fresh', $remainingPaths);
        $this->assertNotContains('/stale', $remainingPaths);
    }

    public function test_it_prunes_old_jsonl_metrics_when_retention_configured(): void
    {
        Storage::fake('local');

        config()->set('ssrmetrics.storage.order', ['jsonl']);
        config()->set('ssrmetrics.storage.database.enabled', false);
        config()->set('ssrmetrics.storage.jsonl.enabled', true);
        config()->set('ssrmetrics.storage.jsonl.disk', 'local');
        config()->set('ssrmetrics.storage.jsonl.path', 'metrics/ssr.jsonl');
        config()->set('ssrmetrics.storage.jsonl.retention_days', 7);

        $now = Carbon::parse('2024-02-01 00:00:00');
        Carbon::setTestNow($now);

        $disk = Storage::disk('local');
        $disk->put('metrics/ssr.jsonl', implode(PHP_EOL, [
            json_encode(['ts' => $now->copy()->subDays(10)->toIso8601String(), 'path' => '/old'], JSON_THROW_ON_ERROR),
            json_encode(['ts' => $now->copy()->subDays(3)->toIso8601String(), 'path' => '/recent'], JSON_THROW_ON_ERROR),
        ]).PHP_EOL);

        $payload = [
            'path' => '/new',
            'score' => 75,
            'html_size' => 1500,
            'meta_count' => 3,
            'og_count' => 1,
            'ldjson_count' => 1,
            'img_count' => 5,
            'blocking_scripts' => 1,
            'first_byte_ms' => 80,
            'collected_at' => $now->toIso8601String(),
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle(app(SsrMetricsService::class));

        $contents = $disk->get('metrics/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));

        $paths = array_map(function (string $line): string {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

            return $decoded['path'];
        }, $lines);

        $this->assertCount(2, $paths);
        $this->assertContains('/recent', $paths);
        $this->assertContains('/new', $paths);
        $this->assertNotContains('/old', $paths);
    }
}
