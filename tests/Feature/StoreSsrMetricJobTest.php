<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
use App\Services\Analytics\SsrAnalyticsService;
use App\Services\Analytics\SsrMetricRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSsrMetricJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_inserts_metric_into_database_when_table_exists(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');

        $now = CarbonImmutable::parse('2024-01-01 12:00:00');
        CarbonImmutable::setTestNow($now);

        $payload = [
            'path' => '/movies',
            'score' => 85,
            'html_size' => 2048,
            'meta_count' => 5,
            'og_count' => 3,
            'ldjson_count' => 1,
            'img_count' => 4,
            'blocking_scripts' => 2,
            'first_byte_ms' => 123,
            'meta' => [
                'first_byte_ms' => 123,
                'html_size' => 2048,
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
        $job->handle(app(SsrMetricRecorder::class));

        $records = app(SsrAnalyticsService::class)->recent();

        $this->assertCount(1, $records);

        $record = $records[0];

        $this->assertSame('/movies', $record['path']);
        $this->assertSame(85, $record['score']);
        $this->assertInstanceOf(CarbonImmutable::class, $record['recorded_at']);
        $this->assertSame($now->toDateTimeString(), $record['recorded_at']->toDateTimeString());

        $normalized = $record['normalized'];

        $this->assertSame(2048, $normalized['html_bytes']);
        $this->assertSame(5, $normalized['counts']['meta']);
        $this->assertSame(3, $normalized['counts']['open_graph']);
        $this->assertSame(1, $normalized['counts']['ldjson']);
        $this->assertSame(4, $normalized['counts']['images']);
        $this->assertSame(2, $normalized['counts']['blocking_scripts']);
        $this->assertSame(123, $normalized['first_byte_ms']);
        $this->assertTrue($normalized['flags']['has_json_ld']);
        $this->assertTrue($normalized['flags']['has_open_graph']);

        $raw = $record['raw'];

        $this->assertSame('/movies', $raw['path']);
        $this->assertSame(85, $raw['score']);
        $this->assertSame(2048, $raw['html_size']);
        $this->assertSame(5, $raw['meta_count']);
        $this->assertSame(3, $raw['og_count']);
        $this->assertSame(1, $raw['ldjson_count']);
        $this->assertSame(4, $raw['img_count']);
        $this->assertSame(2, $raw['blocking_scripts']);
        $this->assertSame(123, $raw['first_byte_ms']);
    }

    public function test_it_appends_metric_to_jsonl_when_table_missing(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');

        $now = CarbonImmutable::parse('2024-01-02 08:30:00');
        CarbonImmutable::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');
        $this->assertFalse(Schema::hasTable('ssr_metrics'));

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
        $job->handle(app(SsrMetricRecorder::class));

        $records = app(SsrAnalyticsService::class)->recent();

        $this->assertCount(1, $records);

        $record = $records[0];

        $this->assertSame('/movies', $record['path']);
        $this->assertSame(70, $record['score']);
        $this->assertSame($now->toDateTimeString(), $record['recorded_at']->toDateTimeString());

        $normalized = $record['normalized'];

        $this->assertSame(1024, $normalized['html_bytes']);
        $this->assertSame(2, $normalized['counts']['meta']);
        $this->assertSame(1, $normalized['counts']['open_graph']);
        $this->assertSame(0, $normalized['counts']['ldjson']);
        $this->assertSame(3, $normalized['counts']['images']);
        $this->assertSame(1, $normalized['counts']['blocking_scripts']);
        $this->assertSame(98, $normalized['first_byte_ms']);
        $this->assertFalse($normalized['flags']['has_json_ld']);
        $this->assertTrue($normalized['flags']['has_open_graph']);

        $this->assertSame($payload, $record['raw']);

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');
    }
}
