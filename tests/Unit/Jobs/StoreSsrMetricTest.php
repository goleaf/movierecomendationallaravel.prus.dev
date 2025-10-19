<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\StoreSsrMetric;
use App\Services\SsrMetricPayloadNormalizer;
use App\Services\SsrMetricRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSsrMetricTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_handle_normalizes_payload_and_writes_to_database(): void
    {
        Storage::fake('local');

        $now = Carbon::parse('2024-04-01 09:45:00');
        Carbon::setTestNow($now);

        $job = new StoreSsrMetric([
            'path' => 'movies/7',
            'movie_id' => '7',
            'score' => 140,
            'html_size' => '4096',
            'meta_count' => '9',
            'og_count' => 0,
            'ldjson_count' => '3',
            'img_count' => '12',
            'blocking_scripts' => '4',
            'first_byte_ms' => '210',
        ]);

        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class),
        );

        $row = DB::table('ssr_metrics')->first();

        $this->assertNotNull($row);
        $this->assertSame('/movies/7', $row->path);
        $this->assertSame(100, (int) $row->score);
        $this->assertSame(210, (int) $row->first_byte_ms);
        $this->assertTrue((bool) $row->has_json_ld);
        $this->assertSame($now->toDateTimeString(), Carbon::parse($row->created_at)->toDateTimeString());

        if (Schema::hasColumn('ssr_metrics', 'meta')) {
            $meta = json_decode((string) $row->meta, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(7, $meta['movie_id']);
            $this->assertSame($now->toIso8601String(), $meta['recorded_at']);
            $this->assertSame(4096, $meta['html_bytes']);
        }

        if (Schema::hasColumn('ssr_metrics', 'normalized_payload')) {
            $normalized = json_decode((string) $row->normalized_payload, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('/movies/7', $normalized['path']);
            $this->assertSame(7, $normalized['movie_id']);
            $this->assertSame($now->toIso8601String(), $normalized['recorded_at']);
            $this->assertSame(4096, $normalized['html_bytes']);
        }

        if (Schema::hasColumn('ssr_metrics', 'movie_id')) {
            $this->assertSame(7, (int) $row->movie_id);
        }

        Storage::disk('local')->assertMissing('metrics/ssr.jsonl');
    }

    public function test_handle_writes_jsonl_payload_when_database_unavailable(): void
    {
        Storage::fake('local');

        Schema::dropIfExists('ssr_metrics');

        $now = Carbon::parse('2024-04-02 11:15:00');
        Carbon::setTestNow($now);

        $job = new StoreSsrMetric([
            'path' => '/hero',
            'movie_id' => null,
            'score' => 63,
            'html_bytes' => 1536,
            'meta_count' => 3,
            'og_count' => 2,
            'ldjson_count' => 0,
            'img_count' => 5,
            'blocking_scripts' => 0,
            'first_byte_ms' => 87,
            'recorded_at' => $now->toIso8601String(),
        ]);

        $job->handle(
            app(SsrMetricPayloadNormalizer::class),
            app(SsrMetricRecorder::class),
        );

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');

        $contents = Storage::disk('local')->get('metrics/ssr.jsonl');
        $lines = array_values(array_filter(explode(PHP_EOL, $contents)));
        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/hero', $decoded['path']);
        $this->assertSame(63, $decoded['score']);
        $this->assertSame($now->toIso8601String(), $decoded['recorded_at']);
        $this->assertArrayHasKey('normalized', $decoded);
        $this->assertSame('/hero', $decoded['normalized']['path']);
        $this->assertSame($now->toIso8601String(), $decoded['normalized']['recorded_at']);
    }
}
