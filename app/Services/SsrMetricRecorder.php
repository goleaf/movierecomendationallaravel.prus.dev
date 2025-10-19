<?php

declare(strict_types=1);

namespace App\Services;

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
    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    public function record(array $normalizedPayload, array $originalPayload = []): void
    {
        $normalizedPayload = $this->ensureNormalizedPayload($normalizedPayload);

        if ($this->storeInDatabase($normalizedPayload)) {
            return;
        }

        $this->storeInJsonl($normalizedPayload, $originalPayload);
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array<string, mixed>
     */
    private function ensureNormalizedPayload(array $normalizedPayload): array
    {
        $recordedAt = Arr::get($normalizedPayload, 'recorded_at')
            ?? Arr::get($normalizedPayload, 'collected_at');

        $recordedAt = $this->castTimestamp($recordedAt);

        $normalizedPayload['recorded_at'] = $recordedAt;
        $normalizedPayload['collected_at'] = $recordedAt;

        return $normalizedPayload;
    }

    private function castTimestamp(mixed $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
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
                // Fall through to now().
            }
        }

        return Carbon::now();
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
            $recordedAt = $normalizedPayload['recorded_at'];

            $data = [
                'path' => $normalizedPayload['path'],
                'score' => $normalizedPayload['score'],
                'created_at' => $recordedAt->toDateTimeString(),
                'updated_at' => $recordedAt->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'movie_id')) {
                $data['movie_id'] = $normalizedPayload['movie_id'];
            }

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $normalizedPayload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $recordedAt->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $data['recorded_at'] = $recordedAt->toDateTimeString();
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

            if (Schema::hasColumn('ssr_metrics', 'normalized_payload')) {
                $data['normalized_payload'] = json_encode($this->normalizedColumnPayload($normalizedPayload), JSON_THROW_ON_ERROR);
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
     */
    private function normalizedColumnPayload(array $normalizedPayload): array
    {
        return [
            'path' => $normalizedPayload['path'],
            'movie_id' => $normalizedPayload['movie_id'],
            'score' => $normalizedPayload['score'],
            'html_bytes' => $normalizedPayload['html_bytes'],
            'meta_count' => $normalizedPayload['meta_count'],
            'og_count' => $normalizedPayload['og_count'],
            'ldjson_count' => $normalizedPayload['ldjson_count'],
            'img_count' => $normalizedPayload['img_count'],
            'blocking_scripts' => $normalizedPayload['blocking_scripts'],
            'first_byte_ms' => $normalizedPayload['first_byte_ms'],
            'has_json_ld' => $normalizedPayload['has_json_ld'],
            'has_open_graph' => $normalizedPayload['has_open_graph'],
            'recorded_at' => $normalizedPayload['recorded_at']->toIso8601String(),
            'meta' => $normalizedPayload['meta'],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    private function storeInJsonl(array $normalizedPayload, array $originalPayload): void
    {
        try {
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $payload = [
                'ts' => $normalizedPayload['recorded_at']->toIso8601String(),
                'recorded_at' => $normalizedPayload['recorded_at']->toIso8601String(),
                'path' => $normalizedPayload['path'],
                'movie_id' => $normalizedPayload['movie_id'],
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
                'normalized' => $this->normalizedColumnPayload($normalizedPayload),
                'original' => $originalPayload,
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $originalPayload,
            ]);
        }
    }
}
