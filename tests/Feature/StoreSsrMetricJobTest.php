<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSsrMetricJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('ssr_metrics');

        Schema::create('ssr_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->unsignedTinyInteger('score');
            $table->unsignedInteger('size');
            $table->unsignedInteger('meta_count');
            $table->unsignedInteger('og_count');
            $table->unsignedInteger('ldjson_count');
            $table->unsignedInteger('img_count');
            $table->unsignedInteger('blocking_scripts');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ssr_metrics');

        parent::tearDown();
    }

    public function test_job_persists_metric_to_database_when_table_exists(): void
    {
        Storage::fake();
        Carbon::setTestNow('2024-03-21 10:00:00');

        $job = new StoreSsrMetric([
            'path' => '/tracked',
            'score' => 82,
            'size' => 512000,
            'meta_count' => 18,
            'og_count' => 4,
            'ldjson_count' => 2,
            'img_count' => 12,
            'blocking_scripts' => 1,
            'captured_at' => now()->toDateTimeString(),
        ]);

        $job->handle();

        $this->assertDatabaseHas('ssr_metrics', [
            'path' => '/tracked',
            'score' => 82,
            'meta_count' => 18,
            'og_count' => 4,
            'ldjson_count' => 2,
            'img_count' => 12,
            'blocking_scripts' => 1,
        ]);

        Carbon::setTestNow();
    }

    public function test_job_appends_metric_to_jsonl_when_table_missing(): void
    {
        Storage::fake();
        Carbon::setTestNow('2024-03-21 11:00:00');

        Schema::dropIfExists('ssr_metrics');

        $job = new StoreSsrMetric([
            'path' => '/fallback',
            'score' => 71,
            'size' => 420000,
            'meta_count' => 12,
            'og_count' => 2,
            'ldjson_count' => 0,
            'img_count' => 8,
            'blocking_scripts' => 3,
            'captured_at' => now()->toDateTimeString(),
        ]);

        $job->handle();

        Storage::disk('local')->assertExists('metrics/ssr.jsonl');
        $content = trim(Storage::disk('local')->get('metrics/ssr.jsonl'));

        $this->assertNotSame('', $content);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('/fallback', $payload['path']);
        $this->assertSame(3, $payload['blocking']);

        Carbon::setTestNow();
    }
}
