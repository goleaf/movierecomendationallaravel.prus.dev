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
            $recordedAt = $this->resolveRecordedAt();

            $data = [
                'path' => $this->payload['path'],
                'score' => $this->payload['score'],
            ];

            if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $data['recorded_at'] = $recordedAt;
            }

            $columnMap = [
                'html_size' => 'html_size',
                'meta_count' => 'meta_count',
                'og_count' => 'og_count',
                'ldjson_count' => 'ldjson_count',
                'img_count' => 'img_count',
                'blocking_scripts' => 'blocking_scripts',
                'first_byte_ms' => 'first_byte_ms',
            ];

            foreach ($columnMap as $column => $payloadKey) {
                if (Schema::hasColumn('ssr_metrics', $column) && array_key_exists($payloadKey, $this->payload)) {
                    $data[$column] = $this->payload[$payloadKey];
                }
            }

            if (Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $data['has_json_ld'] = (bool) ($this->payload['has_json_ld'] ?? (($this->payload['ldjson_count'] ?? 0) > 0));
            }

            if (Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $data['has_open_graph'] = (bool) ($this->payload['has_open_graph'] ?? (($this->payload['og_count'] ?? 0) > 0));
            }

            if (Schema::hasColumn('ssr_metrics', 'created_at')) {
                $data['created_at'] = $recordedAt;
            }

            if (Schema::hasColumn('ssr_metrics', 'updated_at')) {
                $data['updated_at'] = $recordedAt;
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

            $recordedAt = $this->resolveRecordedAt();

            $payload = [
                'ts' => $recordedAt->toIso8601String(),
                'path' => $this->payload['path'],
                'score' => $this->payload['score'],
                'html_size' => $this->payload['html_size'] ?? null,
                'meta_count' => $this->payload['meta_count'] ?? null,
                'og_count' => $this->payload['og_count'] ?? null,
                'ldjson_count' => $this->payload['ldjson_count'] ?? null,
                'img_count' => $this->payload['img_count'] ?? null,
                'blocking_scripts' => $this->payload['blocking_scripts'] ?? null,
                'first_byte_ms' => $this->payload['first_byte_ms'] ?? null,
                'has_json_ld' => (bool) ($this->payload['has_json_ld'] ?? (($this->payload['ldjson_count'] ?? 0) > 0)),
                'has_open_graph' => (bool) ($this->payload['has_open_graph'] ?? (($this->payload['og_count'] ?? 0) > 0)),
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $this->payload,
            ]);
        }
    }

    private function resolveRecordedAt(): Carbon
    {
        if (isset($this->payload['recorded_at'])) {
            try {
                return Carbon::parse((string) $this->payload['recorded_at']);
            } catch (\Throwable) {
                return Carbon::now();
            }
        }

        return Carbon::now();
    }
}
