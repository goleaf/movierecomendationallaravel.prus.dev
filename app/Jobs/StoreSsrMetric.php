<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SsrMetricsNormalizer;
use App\Services\SsrMetricsRecorder;
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
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(SsrMetricsNormalizer $normalizer, SsrMetricsRecorder $recorder): void
    {
        $recorder->record(
            $normalizer->normalize($this->payload),
            $this->payload,
        );
    }
}
