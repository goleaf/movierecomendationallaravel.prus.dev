<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SsrMetricPayloadNormalizer;
use App\Services\SsrMetricRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreSsrMetric implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    private array $normalizedPayload = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(SsrMetricPayloadNormalizer $normalizer, SsrMetricRecorder $recorder): void
    {
        $this->normalizedPayload = $normalizer->normalize($this->payload);

        $recorder->record(
            $this->normalizedPayload,
            $this->payload,
        );
    }
}
