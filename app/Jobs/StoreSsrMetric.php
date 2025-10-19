<?php

declare(strict_types=1);

namespace App\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    public function handle(): void
    {
        if ($this->storeInDatabase()) {
            return;
        }

        $this->storeInJsonl();
    }

    private function storeInDatabase(): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $collectedAt = $this->resolveCollectedAt();
            $htmlBytes = $this->resolveHtmlBytes();

            $data = [
                'path' => $this->payload['path'],
                'score' => $this->payload['score'],
                'first_byte_ms' => $this->resolveInteger('first_byte_ms'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($htmlBytes !== null) {
                if (Schema::hasColumn('ssr_metrics', 'size')) {
                    $data['size'] = $htmlBytes;
                }

                if (Schema::hasColumn('ssr_metrics', 'html_bytes')) {
                    $data['html_bytes'] = $htmlBytes;
                }
            }

            foreach ([
                'meta_count',
                'og_count',
                'ldjson_count',
                'img_count',
                'blocking_scripts',
            ] as $numericKey) {
                if (Schema::hasColumn('ssr_metrics', $numericKey) && isset($this->payload[$numericKey])) {
                    $data[$numericKey] = $this->resolveInteger($numericKey);
                }
            }

            foreach ([
                'has_json_ld' => 'ldjson_count',
                'has_open_graph' => 'og_count',
            ] as $booleanKey => $countSource) {
                if (! Schema::hasColumn('ssr_metrics', $booleanKey)) {
                    continue;
                }

                if (array_key_exists($booleanKey, $this->payload)) {
                    $data[$booleanKey] = (bool) $this->payload[$booleanKey];

                    continue;
                }

                $data[$booleanKey] = $this->resolveBooleanFromCounts($countSource);
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $collectedAt;
            }

            if (Schema::hasColumn('ssr_metrics', 'meta') && isset($this->payload['meta'])) {
                $data['meta'] = json_encode($this->payload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    private function storeInJsonl(): void
    {
        try {
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $collectedAt = $this->resolveCollectedAt();
            $htmlBytes = $this->resolveHtmlBytes();

            $payload = [
                'ts' => now()->toIso8601String(),
                'collected_at' => $collectedAt->toIso8601String(),
                'path' => $this->payload['path'],
                'score' => $this->payload['score'],
                'html_bytes' => $htmlBytes,
                'meta' => $this->payload['meta'] ?? null,
                'meta_count' => $this->payload['meta_count'] ?? null,
                'og' => $this->payload['og_count'] ?? null,
                'ld' => $this->payload['ldjson_count'] ?? null,
                'imgs' => $this->payload['img_count'] ?? null,
                'blocking' => $this->payload['blocking_scripts'] ?? null,
                'first_byte_ms' => $this->resolveInteger('first_byte_ms'),
                'has_json_ld' => $this->resolveBooleanFromCounts('ldjson_count'),
                'has_open_graph' => $this->resolveBooleanFromCounts('og_count'),
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $this->payload,
            ]);
        }
    }

    private function resolveCollectedAt(): Carbon
    {
        $value = $this->payload['collected_at'] ?? null;

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now();
    }

    private function resolveHtmlBytes(): ?int
    {
        $htmlBytes = $this->payload['html_bytes'] ?? $this->payload['html_size'] ?? null;

        if ($htmlBytes === null) {
            return null;
        }

        return (int) $htmlBytes;
    }

    private function resolveInteger(string $key): int
    {
        return (int) ($this->payload[$key] ?? 0);
    }

    private function resolveBooleanFromCounts(string $key): bool
    {
        return $this->resolveInteger($key) > 0;
    }
}
