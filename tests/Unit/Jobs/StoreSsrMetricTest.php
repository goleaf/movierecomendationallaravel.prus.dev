<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\StoreSsrMetric;
use App\Services\SsrMetricsNormalizer;
use App\Services\SsrMetricsRecorder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class StoreSsrMetricTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_job_normalizes_and_records_payload(): void
    {
        $payload = ['path' => '/test', 'score' => 90];
        $normalized = ['path' => '/test', 'score' => 90, 'recorded_at' => now(), 'meta' => [], 'movie' => null, 'html_bytes' => null, 'meta_count' => null, 'og_count' => null, 'ldjson_count' => null, 'img_count' => null, 'blocking_scripts' => null, 'first_byte_ms' => 0, 'has_json_ld' => false, 'has_open_graph' => false];

        $normalizer = Mockery::mock(SsrMetricsNormalizer::class);
        $recorder = Mockery::mock(SsrMetricsRecorder::class);

        $normalizer->shouldReceive('normalize')->once()->with($payload)->andReturn($normalized);
        $recorder->shouldReceive('record')->once()->with($normalized, $payload);

        $job = new StoreSsrMetric($payload);

        $job->handle($normalizer, $recorder);
    }
}
