<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SsrMetricsNormalizer
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
     *     movie: array<string, mixed>|null,
     *     recorded_at: CarbonInterface,
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

        $movieMeta = $this->normalizeMovieMeta($payload['movie'] ?? Arr::get($meta, 'movie'));

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

        if ($movieMeta !== null) {
            $meta['movie'] = $movieMeta;
        } else {
            unset($meta['movie']);
        }

        $recordedAtSource = $payload['recorded_at']
            ?? $payload['collected_at']
            ?? $payload['timestamp']
            ?? $payload['ts']
            ?? null;

        $recordedAt = $this->parseTimestamp($recordedAtSource);

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
            'movie' => $movieMeta,
            'recorded_at' => $recordedAt,
        ];
    }

    public function normalizePath(string $path): string
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

    private function parseTimestamp(mixed $value): CarbonInterface
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
            } catch (\Throwable) {
                // Fallback to now below.
            }
        }

        return Carbon::now();
    }

    private function normalizeMovieMeta(mixed $movie): ?array
    {
        if ($movie instanceof Arrayable) {
            $movie = $movie->toArray();
        }

        if (is_object($movie) && method_exists($movie, 'toArray')) {
            $movie = $movie->toArray();
        }

        if (! is_array($movie)) {
            return null;
        }

        $genres = [];

        if (isset($movie['genres']) && is_array($movie['genres'])) {
            $genres = array_values(array_filter(array_map(static fn ($genre): string => (string) $genre, $movie['genres']), static fn (string $genre): bool => $genre !== ''));
        }

        $normalized = [
            'id' => isset($movie['id']) && is_numeric($movie['id']) ? (int) $movie['id'] : null,
            'title' => isset($movie['title']) ? (string) $movie['title'] : null,
            'slug' => isset($movie['slug']) ? (string) $movie['slug'] : null,
            'imdb_tt' => isset($movie['imdb_tt']) ? (string) $movie['imdb_tt'] : null,
            'release_year' => isset($movie['year']) && is_numeric($movie['year']) ? (int) $movie['year'] : (isset($movie['release_year']) && is_numeric($movie['release_year']) ? (int) $movie['release_year'] : null),
            'release_date' => isset($movie['release_date']) ? (string) $movie['release_date'] : null,
            'poster_url' => isset($movie['poster_url']) ? (string) $movie['poster_url'] : null,
            'imdb_rating' => isset($movie['imdb_rating']) ? (float) $movie['imdb_rating'] : null,
            'imdb_votes' => isset($movie['imdb_votes']) ? (int) $movie['imdb_votes'] : null,
            'runtime_min' => isset($movie['runtime_min']) ? (int) $movie['runtime_min'] : null,
            'type' => isset($movie['type']) ? (string) $movie['type'] : null,
            'genres' => $genres,
        ];

        $normalized = array_filter(
            $normalized,
            static fn ($value) => $value !== null && $value !== [] && $value !== ''
        );

        if ($normalized === []) {
            return null;
        }

        return $normalized;
    }
}
