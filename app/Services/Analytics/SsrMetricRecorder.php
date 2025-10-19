<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Support\SsrMetricPayload;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrMetricRecorder
{
    /**
     * Persist the normalized payload into the database if available.
     *
     * @param  array{path:string,score:int,recorded_at:CarbonImmutable|string,normalized:array<string,mixed>,raw:array<string,mixed>}  $envelope
     */
    public function store(array $envelope): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $recordedAt = $this->resolveRecordedAt($envelope['recorded_at']);

            $raw = $envelope['raw'];
            $normalized = SsrMetricPayload::normalize(array_merge($raw, $envelope['normalized']));

            $data = [
                'path' => $envelope['path'],
                'score' => $normalized['score'],
                'first_byte_ms' => $normalized['first_byte_ms'],
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ];

            if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $data['recorded_at'] = $recordedAt;
            }

            if (Schema::hasColumn('ssr_metrics', 'size') && isset($raw['html_size'])) {
                $data['size'] = (int) $raw['html_size'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count')) {
                $data['meta_count'] = $normalized['counts']['meta'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count')) {
                $data['og_count'] = $normalized['counts']['open_graph'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                $data['ldjson_count'] = $normalized['counts']['ldjson'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count')) {
                $data['img_count'] = $normalized['counts']['images'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                $data['blocking_scripts'] = $normalized['counts']['blocking_scripts'];
            }

            $meta = Arr::get($raw, 'meta');
            if (Schema::hasColumn('ssr_metrics', 'meta') && $meta !== null) {
                $data['meta'] = json_encode($meta, JSON_THROW_ON_ERROR);
            }

            $payloadColumn = $this->payloadColumn();
            if ($payloadColumn !== null) {
                $data[$payloadColumn] = json_encode($raw, JSON_THROW_ON_ERROR);
            }

            $normalizedColumn = $this->normalizedPayloadColumn();
            if ($normalizedColumn !== null) {
                $data[$normalizedColumn] = json_encode($normalized, JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /**
     * Persist the normalized payload to the JSONL fallback storage.
     *
     * @param  array{path:string,score:int,recorded_at:CarbonImmutable|string,normalized:array<string,mixed>,raw:array<string,mixed>}  $envelope
     */
    public function appendFallback(array $envelope): void
    {
        try {
            $disk = $this->storage();

            if (! $disk->exists('metrics')) {
                $disk->makeDirectory('metrics');
            }

            $recordedAt = $this->resolveRecordedAt($envelope['recorded_at']);
            $normalized = SsrMetricPayload::normalize(array_merge($envelope['raw'], $envelope['normalized']));
            $record = SsrMetricPayload::toStorageRecord($normalized, $recordedAt, $envelope['raw']);

            $disk->append('metrics/ssr.jsonl', json_encode($record, JSON_THROW_ON_ERROR));
            $disk->put('metrics/last.json', json_encode([$record], JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $exception,
                'payload' => $envelope['raw'],
            ]);
        }
    }

    private function storage(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.default', 'local'));
    }

    private function resolveRecordedAt(CarbonImmutable|string $value): CarbonImmutable
    {
        return $value instanceof CarbonImmutable
            ? $value
            : CarbonImmutable::parse($value);
    }

    private function payloadColumn(): ?string
    {
        return $this->resolveColumn(['payload', 'raw_payload']);
    }

    private function normalizedPayloadColumn(): ?string
    {
        return $this->resolveColumn(['normalized_payload', 'payload_normalized']);
    }

    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('ssr_metrics', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
