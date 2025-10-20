<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\SsrMetricsFallbackStore;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SsrMetricsRecorder
{
    public function __construct(private readonly SsrMetricsFallbackStore $fallbackStore) {}

    /**
     * @param  array{
     *     path: string,
     *     score: int,
     *     html_bytes: int|null,
     *     meta_count: int|null,
     *     og_count: int|null,
     *     ldjson_count: int|null,
     *     img_count: int|null,
     *     blocking_scripts: int|null,
     *     first_byte_ms: int,
     *     has_json_ld: bool,
     *     has_open_graph: bool,
     *     meta: array<string, mixed>,
     *     movie: array<string, mixed>|null,
     *     recorded_at: CarbonInterface,
     * }  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    public function record(array $normalizedPayload, array $originalPayload = []): void
    {
        if ($this->storeInDatabase($normalizedPayload)) {
            $this->prunePrimaryRetention();

            return;
        }

        $this->storeInJsonl($normalizedPayload, $originalPayload);
        $this->pruneFallbackRetention();
    }

    /**
     * @param  array{
     *     path: string,
     *     score: int,
     *     html_bytes: int|null,
     *     meta_count: int|null,
     *     og_count: int|null,
     *     ldjson_count: int|null,
     *     img_count: int|null,
     *     blocking_scripts: int|null,
     *     first_byte_ms: int,
     *     has_json_ld: bool,
     *     has_open_graph: bool,
     *     meta: array<string, mixed>,
     *     movie: array<string, mixed>|null,
     *     recorded_at: CarbonInterface,
     * }  $normalizedPayload
     */
    private function storeInDatabase(array $normalizedPayload): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $recordedAt = $normalizedPayload['recorded_at'];

            $data = [
                'path' => $normalizedPayload['path'],
                'score' => $normalizedPayload['score'],
                'created_at' => $recordedAt->toDateTimeString(),
                'updated_at' => $recordedAt->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $normalizedPayload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $data['recorded_at'] = $recordedAt->toDateTimeString();
            } elseif (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $recordedAt->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'size') && $normalizedPayload['html_bytes'] !== null) {
                $data['size'] = $normalizedPayload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'html_bytes') && $normalizedPayload['html_bytes'] !== null) {
                $data['html_bytes'] = $normalizedPayload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count') && $normalizedPayload['meta_count'] !== null) {
                $data['meta_count'] = $normalizedPayload['meta_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count') && $normalizedPayload['og_count'] !== null) {
                $data['og_count'] = $normalizedPayload['og_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count') && $normalizedPayload['ldjson_count'] !== null) {
                $data['ldjson_count'] = $normalizedPayload['ldjson_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count') && $normalizedPayload['img_count'] !== null) {
                $data['img_count'] = $normalizedPayload['img_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts') && $normalizedPayload['blocking_scripts'] !== null) {
                $data['blocking_scripts'] = $normalizedPayload['blocking_scripts'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $data['has_json_ld'] = $normalizedPayload['has_json_ld'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $data['has_open_graph'] = $normalizedPayload['has_open_graph'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta')) {
                $data['meta'] = json_encode($normalizedPayload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * @param  array{
     *     path: string,
     *     score: int,
     *     html_bytes: int|null,
     *     meta_count: int|null,
     *     og_count: int|null,
     *     ldjson_count: int|null,
     *     img_count: int|null,
     *     blocking_scripts: int|null,
     *     first_byte_ms: int,
     *     has_json_ld: bool,
     *     has_open_graph: bool,
     *     meta: array<string, mixed>,
     *     movie: array<string, mixed>|null,
     *     recorded_at: CarbonInterface,
     * }  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    private function storeInJsonl(array $normalizedPayload, array $originalPayload): void
    {
        try {
            $recordedAt = $normalizedPayload['recorded_at']->toIso8601String();

            $payload = [
                'ts' => $recordedAt,
                'recorded_at' => $recordedAt,
                'path' => $normalizedPayload['path'],
                'score' => $normalizedPayload['score'],
                'html_bytes' => $normalizedPayload['html_bytes'],
                'size' => $normalizedPayload['html_bytes'],
                'html_size' => $normalizedPayload['html_bytes'],
                'meta' => $normalizedPayload['meta'],
                'meta_count' => $normalizedPayload['meta_count'],
                'og_count' => $normalizedPayload['og_count'],
                'og' => $normalizedPayload['og_count'],
                'ldjson_count' => $normalizedPayload['ldjson_count'],
                'ld' => $normalizedPayload['ldjson_count'],
                'img_count' => $normalizedPayload['img_count'],
                'imgs' => $normalizedPayload['img_count'],
                'blocking_scripts' => $normalizedPayload['blocking_scripts'],
                'blocking' => $normalizedPayload['blocking_scripts'],
                'first_byte_ms' => $normalizedPayload['first_byte_ms'],
                'has_json_ld' => $normalizedPayload['has_json_ld'],
                'has_open_graph' => $normalizedPayload['has_open_graph'],
            ];

            if ($normalizedPayload['movie'] !== null) {
                $payload['movie'] = $normalizedPayload['movie'];
            } elseif (isset($originalPayload['movie'])) {
                $payload['movie'] = $originalPayload['movie'];
            }

            $this->fallbackStore->append($payload);
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $originalPayload,
            ]);
        }
    }

    private function prunePrimaryRetention(): void
    {
        $days = (int) config('ssrmetrics.retention.primary_days', 0);

        if ($days <= 0) {
            return;
        }

        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        $timestampColumn = $this->timestampColumn();

        $cutoff = Carbon::now()->subDays($days);

        DB::table('ssr_metrics')
            ->whereNotNull($timestampColumn)
            ->where($timestampColumn, '<', $cutoff->toDateTimeString())
            ->delete();
    }

    private function pruneFallbackRetention(): void
    {
        $days = (int) config('ssrmetrics.retention.fallback_days', 0);

        if ($days <= 0) {
            return;
        }

        $records = $this->fallbackStore->readIncoming();

        if ($records === []) {
            return;
        }

        $cutoff = Carbon::now()->subDays($days);

        $filtered = array_values(array_filter($records, function (array $record) use ($cutoff): bool {
            $timestamp = $this->resolveFallbackTimestamp($record);

            if ($timestamp === null) {
                return true;
            }

            return $timestamp->greaterThanOrEqualTo($cutoff);
        }));

        if (count($filtered) === count($records)) {
            return;
        }

        $disk = Storage::disk($this->fallbackStore->diskName());
        $path = (string) config('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        if ($filtered === []) {
            $disk->delete($path);

            return;
        }

        $lines = array_map(static fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR), $filtered);

        $disk->put($path, implode("\n", $lines));
    }

    private function timestampColumn(): string
    {
        if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
            return 'recorded_at';
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            return 'collected_at';
        }

        if (Schema::hasColumn('ssr_metrics', 'created_at')) {
            return 'created_at';
        }

        return 'updated_at';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveFallbackTimestamp(array $record): ?CarbonInterface
    {
        $value = Arr::get($record, 'recorded_at')
            ?? Arr::get($record, 'ts')
            ?? Arr::get($record, 'collected_at')
            ?? Arr::get($record, 'timestamp');

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::createFromInterface($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
