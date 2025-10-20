<?php

declare(strict_types=1);

namespace App\Services;

class SsrMetricPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
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
     *     collected_at: \DateTimeInterface|int|string|null,
     *     recorded_at: \DateTimeInterface|int|string|null,
     * }
     */
    public function normalize(array $payload): array
    {
        $path = isset($payload['path']) ? (string) $payload['path'] : '/';
        $path = $this->normalizePath($path);

        $score = max(0, min(100, (int) ($payload['score'] ?? 0)));

        $htmlBytes = $this->extractInteger($payload, ['html_bytes', 'html_size', 'size']);
        $metaCount = $this->extractInteger($payload, ['meta_count']);
        $ogCount = $this->extractInteger($payload, ['og_count']);
        $ldjsonCount = $this->extractInteger($payload, ['ldjson_count']);
        $imgCount = $this->extractInteger($payload, ['img_count']);
        $blockingScripts = $this->extractInteger($payload, ['blocking_scripts']);
        $firstByteMs = $this->extractInteger($payload, ['first_byte_ms']) ?? 0;

        $hasJsonLd = array_key_exists('has_json_ld', $payload)
            ? (bool) $payload['has_json_ld']
            : (($ldjsonCount ?? 0) > 0);

        $hasOpenGraph = array_key_exists('has_open_graph', $payload)
            ? (bool) $payload['has_open_graph']
            : (($ogCount ?? 0) > 0);

        $meta = $payload['meta'] ?? [];
        $meta = is_array($meta) ? $meta : [];
        $meta = array_merge($meta, [
            'first_byte_ms' => $firstByteMs,
            'html_bytes' => $htmlBytes,
            'html_size' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'has_json_ld' => $hasJsonLd,
            'has_open_graph' => $hasOpenGraph,
        ]);

        $recordedAtSource = $payload['recorded_at']
            ?? $payload['collected_at']
            ?? $payload['timestamp']
            ?? $payload['ts']
            ?? null;

        $collectedAtSource = $payload['collected_at'] ?? $recordedAtSource;

        return [
            'path' => $path,
            'score' => $score,
            'html_bytes' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'first_byte_ms' => $firstByteMs,
            'has_json_ld' => $hasJsonLd,
            'has_open_graph' => $hasOpenGraph,
            'meta' => $meta,
            'collected_at' => $collectedAtSource,
            'recorded_at' => $recordedAtSource,
        ];
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/'.ltrim($path, '/');

        return $normalized === '//' ? '/' : $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function extractInteger(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null) {
                return null;
            }

            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        return null;
    }
}
