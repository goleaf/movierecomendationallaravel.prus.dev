<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Analytics\SsrMetricRecorder;
use App\Support\SsrMetricPayload;
use Carbon\CarbonImmutable;
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

    public function handle(SsrMetricRecorder $recorder): void
    {
        $recordedAt = CarbonImmutable::now();
        $normalized = SsrMetricPayload::normalize($this->payload);

        $envelope = [
            'path' => $normalized['path'],
            'score' => $normalized['score'],
            'recorded_at' => $recordedAt,
            'normalized' => $normalized,
            'raw' => $this->payload,
        ];

        if ($recorder->store($envelope)) {
            return;
        }

        $recorder->appendFallback($envelope);
    }
}
