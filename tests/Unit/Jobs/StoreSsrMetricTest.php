<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\StoreSsrMetric;
use App\Services\SsrMetricPayloadNormalizer;
use App\Services\SsrMetricRecorder;
use Mockery;
use Tests\TestCase;

class StoreSsrMetricTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_normalizes_and_records_payload(): void
    {
        $payload = ['path' => '/movies', 'score' => 88];
        $normalized = $payload + [
            'html_bytes' => null,
            'meta_count' => null,
            'og_count' => null,
            'ldjson_count' => null,
            'img_count' => null,
            'blocking_scripts' => null,
            'first_byte_ms' => 0,
            'has_json_ld' => false,
            'has_open_graph' => false,
            'meta' => [],
            'collected_at' => '2024-01-01T00:00:00Z',
            'recorded_at' => '2024-01-01T00:00:00Z',
        ];

        $normalizer = Mockery::mock(SsrMetricPayloadNormalizer::class);
        $recorder = Mockery::mock(SsrMetricRecorder::class);

        $normalizer->expects('normalize')->with($payload)->andReturn($normalized);
        $recorder->expects('record')->with($normalized, $payload);

        $job = new StoreSsrMetric($payload);
        $job->handle($normalizer, $recorder);

        $this->addToAssertionCount(1);
    }
}
