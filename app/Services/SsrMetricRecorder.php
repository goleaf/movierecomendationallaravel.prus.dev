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

class SsrMetricRecorder
{
    public function __construct(private readonly SsrMetricsFallbackStore $fallbackStore) {}

    /**
     * @param  array{collected_at: mixed, recorded_at: mixed}  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    public function record(array $normalizedPayload, array $originalPayload = []): void
    {
        $normalizedPayload = $this->ensureTimestamps($normalizedPayload);

        if ($this->storeInDatabase($normalizedPayload)) {
            $this->prunePrimaryRetention();

            return;
        }

        $this->storeInJsonl($normalizedPayload, $originalPayload);
        $this->pruneFallbackRetention();
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array<string, mixed>
     */
    private function ensureTimestamps(array $normalizedPayload): array
    {
        $collectedAt = $this->resolveTimestamp(Arr::get($normalizedPayload, 'collected_at'));
        $recordedAt = $this->resolveTimestamp(Arr::get($normalizedPayload, 'recorded_at'));

        if ($collectedAt === null && $recordedAt !== null) {
            $collectedAt = $recordedAt;
        }

        if ($recordedAt === null && $collectedAt !== null) {
            $recordedAt = $collectedAt;
        }

        if ($collectedAt === null) {
            $collectedAt = Carbon::now();
        }

        if ($recordedAt === null) {
            $recordedAt = $collectedAt;
        }

        $normalizedPayload['collected_at'] = $collectedAt;
        $normalizedPayload['recorded_at'] = $recordedAt;

        return $normalizedPayload;
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     */
    private function storeInDatabase(array $normalizedPayload): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            /** @var CarbonInterface $collectedAt */
            $collectedAt = $normalizedPayload['collected_at'];
            /** @var CarbonInterface $recordedAt */
            $recordedAt = $normalizedPayload['recorded_at'];

            $data = [
                'path' => $normalizedPayload['path'],
                'score' => $normalizedPayload['score'],
                'created_at' => $recordedAt->toDateTimeString(),
                'updated_at' => $recordedAt->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $data['recorded_at'] = $recordedAt->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $collectedAt->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $normalizedPayload['first_byte_ms'];
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
     * @param  array<string, mixed>  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    private function storeInJsonl(array $normalizedPayload, array $originalPayload): void
    {
        try {
            /** @var CarbonInterface $collectedAt */
            $collectedAt = $normalizedPayload['collected_at'];
            /** @var CarbonInterface $recordedAt */
            $recordedAt = $normalizedPayload['recorded_at'];

            $payload = [
                'ts' => $collectedAt->toIso8601String(),
                'recorded_at' => $recordedAt->toIso8601String(),
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
        if (! Schema::hasTable('ssr_metrics')) {
            return 'created_at';
        }

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

    private function resolveTimestamp(mixed $value): ?CarbonInterface
    {
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

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveFallbackTimestamp(array $record): ?CarbonInterface
    {
        $value = $record['recorded_at']
            ?? $record['ts']
            ?? $record['collected_at']
            ?? $record['timestamp']
            ?? null;

        return $this->resolveTimestamp($value);
    }
}
