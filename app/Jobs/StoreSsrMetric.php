<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreSsrMetric implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $metric
     */
    public function __construct(private array $metric) {}

    public function handle(): void
    {
        $capturedAt = Carbon::parse($this->metric['captured_at'] ?? now());

        if ($this->writeToDatabase($capturedAt)) {
            return;
        }

        $this->appendToJsonl($capturedAt);
    }

    private function writeToDatabase(Carbon $capturedAt): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            DB::table('ssr_metrics')->insert([
                'path' => $this->metric['path'],
                'score' => $this->metric['score'],
                'size' => $this->metric['size'],
                'meta_count' => $this->metric['meta_count'],
                'og_count' => $this->metric['og_count'],
                'ldjson_count' => $this->metric['ldjson_count'],
                'img_count' => $this->metric['img_count'],
                'blocking_scripts' => $this->metric['blocking_scripts'],
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ]);

            return true;
        } catch (Throwable $exception) {
            report($exception);
        }

        return false;
    }

    private function appendToJsonl(Carbon $capturedAt): void
    {
        try {
            Storage::append('metrics/ssr.jsonl', json_encode([
                'ts' => $capturedAt->toIso8601String(),
                'path' => $this->metric['path'],
                'score' => $this->metric['score'],
                'size' => $this->metric['size'],
                'meta' => $this->metric['meta_count'],
                'og' => $this->metric['og_count'],
                'ld' => $this->metric['ldjson_count'],
                'imgs' => $this->metric['img_count'],
                'blocking' => $this->metric['blocking_scripts'],
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
