<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\StoreSsrMetric;
use App\Services\Analytics\SsrMetricRecorder;
use App\Support\SsrMetricPayload;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class StoreSsrMetricTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_job_stores_normalized_payload_via_recorder(): void
    {
        CarbonImmutable::setTestNow('2024-02-01 10:00:00');

        $payload = [
            'path' => '/test',
            'score' => 82,
            'html_size' => 4096,
            'meta_count' => 6,
            'og_count' => 2,
            'ldjson_count' => 1,
            'img_count' => 5,
            'blocking_scripts' => 0,
            'first_byte_ms' => 150,
            'meta' => [
                'first_byte_ms' => 150,
                'html_size' => 4096,
                'meta_count' => 6,
                'og_count' => 2,
                'ldjson_count' => 1,
                'img_count' => 5,
                'blocking_scripts' => 0,
                'has_json_ld' => true,
                'has_open_graph' => true,
            ],
        ];

        $expectedNormalized = SsrMetricPayload::normalize($payload);

        $recorder = Mockery::mock(SsrMetricRecorder::class);
        $recorder->shouldReceive('store')
            ->once()
            ->with(Mockery::on(function (array $envelope) use ($expectedNormalized, $payload): bool {
                return $envelope['path'] === $expectedNormalized['path']
                    && $envelope['score'] === $expectedNormalized['score']
                    && $envelope['normalized'] === $expectedNormalized
                    && $envelope['raw'] === $payload
                    && $envelope['recorded_at'] instanceof CarbonImmutable
                    && $envelope['recorded_at']->equalTo(CarbonImmutable::now());
            }))
            ->andReturnTrue();
        $recorder->shouldNotReceive('appendFallback');

        (new StoreSsrMetric($payload))->handle($recorder);
    }

    public function test_job_falls_back_to_jsonl_when_store_fails(): void
    {
        CarbonImmutable::setTestNow('2024-02-01 11:00:00');

        $payload = [
            'path' => '/fallback',
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

        $expectedNormalized = SsrMetricPayload::normalize($payload);
        $capturedEnvelope = null;

        $recorder = Mockery::mock(SsrMetricRecorder::class);
        $recorder->shouldReceive('store')
            ->once()
            ->with(Mockery::on(function (array $envelope) use ($expectedNormalized, $payload, &$capturedEnvelope): bool {
                $capturedEnvelope = $envelope;

                return $envelope['path'] === $expectedNormalized['path']
                    && $envelope['score'] === $expectedNormalized['score']
                    && $envelope['normalized'] === $expectedNormalized
                    && $envelope['raw'] === $payload
                    && $envelope['recorded_at'] instanceof CarbonImmutable
                    && $envelope['recorded_at']->equalTo(CarbonImmutable::now());
            }))
            ->andReturnFalse();

        $recorder->shouldReceive('appendFallback')
            ->once()
            ->with(Mockery::on(function (array $envelope) use (&$capturedEnvelope): bool {
                return $envelope === $capturedEnvelope;
            }));

        (new StoreSsrMetric($payload))->handle($recorder);
    }
}
