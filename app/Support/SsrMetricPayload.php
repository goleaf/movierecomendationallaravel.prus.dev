<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class SsrMetricPayload
{
    /**
     * Normalize a raw SSR payload or partially-normalized payload structure.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     path: string,
     *     score: int,
     *     first_byte_ms: int,
     *     html_bytes: int,
     *     counts: array{
     *         meta: int,
     *         open_graph: int,
     *         ldjson: int,
     *         images: int,
     *         blocking_scripts: int
     *     },
     *     flags: array{
     *         has_json_ld: bool,
     *         has_open_graph: bool
     *     }
     * }
     */
    public static function normalize(array $payload): array
    {
        $path = (string) ($payload['path'] ?? Arr::get($payload, 'normalized.path') ?? '/');
        $score = (int) ($payload['score'] ?? Arr::get($payload, 'normalized.score') ?? 0);

        $firstByte = (int) (
            $payload['first_byte_ms']
            ?? Arr::get($payload, 'timings.first_byte_ms')
            ?? Arr::get($payload, 'normalized.first_byte_ms')
            ?? 0
        );

        $htmlBytes = (int) (
            $payload['html_size']
            ?? $payload['html_bytes']
            ?? Arr::get($payload, 'sizes.html_bytes')
            ?? Arr::get($payload, 'normalized.html_bytes')
            ?? $payload['size']
            ?? 0
        );

        $metaCount = (int) (
            Arr::get($payload, 'counts.meta')
            ?? Arr::get($payload, 'meta.meta_count')
            ?? $payload['meta_count']
            ?? 0
        );

        $ogCount = (int) (
            Arr::get($payload, 'counts.open_graph')
            ?? Arr::get($payload, 'meta.og_count')
            ?? $payload['og_count']
            ?? $payload['og']
            ?? 0
        );

        $ldjsonCount = (int) (
            Arr::get($payload, 'counts.ldjson')
            ?? Arr::get($payload, 'meta.ldjson_count')
            ?? $payload['ldjson_count']
            ?? $payload['ld']
            ?? 0
        );

        $imgCount = (int) (
            Arr::get($payload, 'counts.images')
            ?? Arr::get($payload, 'meta.img_count')
            ?? $payload['img_count']
            ?? $payload['imgs']
            ?? 0
        );

        $blockingScripts = (int) (
            Arr::get($payload, 'counts.blocking_scripts')
            ?? Arr::get($payload, 'meta.blocking_scripts')
            ?? $payload['blocking_scripts']
            ?? $payload['blocking']
            ?? 0
        );

        $hasJsonLd = (bool) (
            Arr::get($payload, 'flags.has_json_ld')
            ?? Arr::get($payload, 'meta.has_json_ld')
            ?? ($ldjsonCount > 0)
        );

        $hasOpenGraph = (bool) (
            Arr::get($payload, 'flags.has_open_graph')
            ?? Arr::get($payload, 'meta.has_open_graph')
            ?? ($ogCount > 0)
        );

        return [
            'path' => $path,
            'score' => $score,
            'first_byte_ms' => $firstByte,
            'html_bytes' => $htmlBytes,
            'counts' => [
                'meta' => $metaCount,
                'open_graph' => $ogCount,
                'ldjson' => $ldjsonCount,
                'images' => $imgCount,
                'blocking_scripts' => $blockingScripts,
            ],
            'flags' => [
                'has_json_ld' => $hasJsonLd,
                'has_open_graph' => $hasOpenGraph,
            ],
        ];
    }

    /**
     * Build a fallback storage record preserving the normalized structure.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function toStorageRecord(array $normalized, CarbonImmutable $recordedAt, array $raw): array
    {
        return [
            'recorded_at' => $recordedAt->toIso8601String(),
            'path' => $normalized['path'],
            'score' => $normalized['score'],
            'first_byte_ms' => $normalized['first_byte_ms'],
            'html_bytes' => $normalized['html_bytes'],
            'counts' => $normalized['counts'],
            'flags' => $normalized['flags'],
            'raw' => $raw,
        ];
    }
}
