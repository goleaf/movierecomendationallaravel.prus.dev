<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
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
        $collectedAt = $now->copy()->subMinutes(5);

        $payload = [
            'path' => '/movies',
            'score' => 85,
            'html_bytes' => 2048,
            'meta_count' => 5,
            'og_count' => 3,
            'ldjson_count' => 1,
            'img_count' => 4,
            'blocking_scripts' => 2,
            'first_byte_ms' => 123,
            'has_json_ld' => true,
            'has_open_graph' => true,
            'collected_at' => $collectedAt,
            'meta' => [
                'first_byte_ms' => 123,
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
        $job->handle();

        $row = DB::table('ssr_metrics')->first();

        $this->assertNotNull($row);
        $this->assertSame('/movies', $row->path);
        $this->assertSame(85, (int) $row->score);
        $this->assertSame(2048, (int) $row->html_bytes);
        $this->assertSame(2048, (int) $row->size);
        $this->assertSame(5, (int) $row->meta_count);
        $this->assertSame(3, (int) $row->og_count);
        $this->assertSame(1, (int) $row->ldjson_count);
        $this->assertSame(4, (int) $row->img_count);
        $this->assertSame(2, (int) $row->blocking_scripts);
        $this->assertTrue((bool) $row->has_json_ld);
        $this->assertTrue((bool) $row->has_open_graph);
        $this->assertSame($collectedAt->toDateTimeString(), Carbon::parse($row->collected_at)->toDateTimeString());
        $this->assertSame($now->toDateTimeString(), Carbon::parse($row->created_at)->toDateTimeString());
    }

    public function test_it_appends_metric_to_jsonl_when_table_missing(): void
    {
        Storage::fake('local');

        $now = Carbon::parse('2024-01-02 08:30:00');
        Carbon::setTestNow($now);

        Schema::dropIfExists('ssr_metrics');

        $payload = [
            'path' => '/movies',
            'score' => 70,
            'html_bytes' => 1024,
            'meta_count' => 2,
            'og_count' => 1,
            'ldjson_count' => 0,
            'img_count' => 3,
            'blocking_scripts' => 1,
            'first_byte_ms' => 98,
            'collected_at' => '2024-01-02T08:00:00+00:00',
            'meta' => [
                'first_byte_ms' => 98,
                'html_bytes' => 1024,
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
        $job->handle();

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');

        $contents = Storage::disk('local')->get('metrics/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($now->toIso8601String(), $decoded['ts']);
        $this->assertSame('2024-01-02T08:00:00+00:00', $decoded['collected_at']);
        $this->assertSame('/movies', $decoded['path']);
        $this->assertSame(70, $decoded['score']);
        $this->assertSame(1024, $decoded['html_bytes']);
        $this->assertSame(2, $decoded['meta_count']);
        $this->assertSame(1, $decoded['og']);
        $this->assertSame(0, $decoded['ld']);
        $this->assertSame(3, $decoded['imgs']);
        $this->assertSame(1, $decoded['blocking']);
        $this->assertSame(98, $decoded['first_byte_ms']);
        $this->assertFalse($decoded['has_json_ld']);
        $this->assertTrue($decoded['has_open_graph']);
    }
}
