<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SsrMetricsService
{
    public function shouldCapture(Request $request, Response $response): bool
    {
        if (! config('ssrmetrics.enabled')) {
            return false;
        }

        if (! in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return false;
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 400) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if ($contentType === '' || ! str_contains($contentType, 'text/html')) {
            return false;
        }

        $path = $this->normalizePath($request->path());

        $paths = collect(config('ssrmetrics.paths', []))
            ->filter(static fn ($value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => $this->normalizePath($value))
            ->values();

        if ($paths->isEmpty()) {
            return true;
        }

        if ($paths->contains('*')) {
            return true;
        }

        return $paths->contains($path);
    }

    public function makePayload(Request $request, Response $response, float $startedAt): array
    {
        $path = $this->normalizePath($request->path());
        $firstByteMs = $this->calculateFirstByteMs($startedAt);
        $metrics = $this->parseResponse($response);

        return $this->buildPayload($path, $firstByteMs, $metrics);
    }

    private function normalizePath(?string $path): string
    {
        $path ??= '/';

        $normalized = '/'.ltrim($path, '/');

        return $normalized === '//' ? '/' : $normalized;
    }

    private function calculateFirstByteMs(float $startedAt, ?float $finishedAt = null): int
    {
        $finishedAt ??= microtime(true);

        if ($finishedAt <= $startedAt) {
            return 0;
        }

        return max(0, (int) round(($finishedAt - $startedAt) * 1000));
    }

    /**
     * Extracts SSR-related metrics from an HTML response.
     */
    public function parseResponse(Response $response): array
    {
        $html = $response->getContent() ?? '';

        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        return [
            'html_bytes' => $size,
            'html_size' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'has_json_ld' => $ld > 0,
            'has_open_graph' => $og > 0,
        ];
    }

    /**
     * Compute the SSR score using configured penalties.
     */
    public function computeScore(array $metrics): int
    {
        $score = 100;

        $blocking = (int) ($metrics['blocking_scripts'] ?? 0);

        if ($blocking > 0) {
            $perScriptPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.per_script', 5);
            $maxPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.max', 30);

            $score -= min($maxPenalty, $perScriptPenalty * $blocking);
        }

        $ld = (int) ($metrics['ldjson_count'] ?? 0);

        if ($ld === 0) {
            $score -= (int) config('ssrmetrics.penalties.missing_ldjson.deduction', 10);
        }

        $og = (int) ($metrics['og_count'] ?? 0);
        $minimumOgTags = (int) config('ssrmetrics.penalties.low_og.minimum', 3);

        if ($og < $minimumOgTags) {
            $score -= (int) config('ssrmetrics.penalties.low_og.deduction', 10);
        }

        $size = (int) ($metrics['html_size'] ?? 0);
        $oversizedThreshold = (int) config('ssrmetrics.penalties.oversized_html.threshold', 900 * 1024);

        if ($size > $oversizedThreshold) {
            $score -= (int) config('ssrmetrics.penalties.oversized_html.deduction', 20);
        }

        $imgs = (int) ($metrics['img_count'] ?? 0);
        $excessImageThreshold = (int) config('ssrmetrics.penalties.excess_images.threshold', 60);

        if ($imgs > $excessImageThreshold) {
            $score -= (int) config('ssrmetrics.penalties.excess_images.deduction', 10);
        }

        return max(0, $score);
    }

    /**
     * Assemble the payload dispatched to StoreSsrMetric.
     */
    public function buildPayload(string $path, int $firstByteMs, array $metrics, ?int $score = null): array
    {
        $score ??= $this->computeScore($metrics);

        $metaPayload = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $metrics['html_size'] ?? 0,
            'html_bytes' => $metrics['html_bytes'] ?? $metrics['html_size'] ?? 0,
            'meta_count' => $metrics['meta_count'] ?? 0,
            'og_count' => $metrics['og_count'] ?? 0,
            'ldjson_count' => $metrics['ldjson_count'] ?? 0,
            'img_count' => $metrics['img_count'] ?? 0,
            'blocking_scripts' => $metrics['blocking_scripts'] ?? 0,
            'has_json_ld' => $metrics['has_json_ld'] ?? false,
            'has_open_graph' => $metrics['has_open_graph'] ?? false,
        ];

        return array_merge(
            [
                'path' => $path,
                'score' => $score,
                'collected_at' => now()->toIso8601String(),
                'first_byte_ms' => $firstByteMs,
                'meta' => $metaPayload,
            ],
            $metrics,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeMetric(array $payload): void
    {
        $this->storeNormalizedMetric(
            $this->normalizePayload($payload),
            $payload,
        );
    }

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
     *     collected_at: CarbonInterface,
     * }
     */
    public function normalizePayload(array $payload): array
    {
        $path = $this->normalizePath($payload['path'] ?? '/');

        $score = (int) ($payload['score'] ?? 0);
        $score = max(0, min(100, $score));

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

        $collectedAtSource = $payload['collected_at']
            ?? $payload['timestamp']
            ?? $payload['ts']
            ?? null;

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
            'collected_at' => $this->resolveTimestamp($collectedAtSource),
        ];
    }

    /**
     * @param  array{collected_at: CarbonInterface|\DateTimeInterface|string|int|null}  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    public function storeNormalizedMetric(array $normalizedPayload, array $originalPayload = []): void
    {
        $normalizedPayload = $this->ensureNormalizedPayload($normalizedPayload);

        if ($this->storeInDatabase($normalizedPayload)) {
            $this->cleanupDatabaseRetention($normalizedPayload['collected_at']);
            $this->cleanupJsonlRetention($normalizedPayload['collected_at']);

            return;
        }

        $this->storeInJsonl($normalizedPayload, $originalPayload);
        $this->cleanupJsonlRetention($normalizedPayload['collected_at']);
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array<string, mixed>
     */
    private function ensureNormalizedPayload(array $normalizedPayload): array
    {
        $collectedAt = Arr::get($normalizedPayload, 'collected_at');

        if (! $collectedAt instanceof CarbonInterface) {
            $collectedAt = $this->attemptParseTimestamp($collectedAt) ?? Carbon::now();
        }

        $normalizedPayload['collected_at'] = $collectedAt;

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
            $collectedAt = $normalizedPayload['collected_at'];

            $data = [
                'path' => $normalizedPayload['path'],
                'score' => $normalizedPayload['score'],
                'created_at' => $collectedAt->toDateTimeString(),
                'updated_at' => $collectedAt->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $normalizedPayload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $collectedAt->toDateTimeString();
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
        $disk = $this->storageDisk('fallback');
        $path = $this->storagePath('fallback', 'incoming');

        try {
            $filesystem = Storage::disk($disk);

            $this->ensureDirectoryExists($disk, $path);

            $payload = [
                'ts' => $normalizedPayload['collected_at']->toIso8601String(),
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

            $filesystem->append($path, json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $originalPayload,
                'disk' => $disk,
                'path' => $path,
            ]);
        }
    }

    public function storageDisk(string $tier): string
    {
        $disk = (string) Arr::get(config('ssrmetrics.storage', []), $tier.'.disk', 'local');
        $configuredDisks = array_keys(config('filesystems.disks', []));

        if ($disk === '' || ! in_array($disk, $configuredDisks, true)) {
            return 'local';
        }

        return $disk;
    }

    public function storagePath(string $tier, string $fileKey): string
    {
        $default = match ($tier.'.'.$fileKey) {
            'primary.incoming' => 'ssr-metrics.jsonl',
            'primary.aggregate' => 'ssr-metrics-summary.json',
            'fallback.recovery' => 'ssr-metrics-recovery.jsonl',
            default => 'ssr-metrics-fallback.jsonl',
        };

        $path = (string) Arr::get(config('ssrmetrics.storage', []), $tier.'.files.'.$fileKey, $default);

        return $path !== '' ? $path : $default;
    }

    private function cleanupDatabaseRetention(CarbonInterface $reference): void
    {
        $days = (int) config('ssrmetrics.retention.primary_days', 0);

        if ($days <= 0 || ! Schema::hasTable('ssr_metrics')) {
            return;
        }

        $column = $this->databaseTimestampColumn();

        if ($column === null) {
            return;
        }

        $cutoff = $reference->copy()->subDays($days);

        try {
            DB::table('ssr_metrics')
                ->whereNotNull($column)
                ->where($column, '<', $cutoff->toDateTimeString())
                ->delete();
        } catch (Throwable $e) {
            Log::warning('Failed pruning SSR metric records.', [
                'exception' => $e,
            ]);
        }
    }

    private function cleanupJsonlRetention(CarbonInterface $reference): void
    {
        $days = (int) config('ssrmetrics.retention.fallback_days', 0);

        if ($days <= 0) {
            return;
        }

        $disk = $this->storageDisk('fallback');
        $path = $this->storagePath('fallback', 'incoming');
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            return;
        }

        try {
            $contents = $filesystem->get($path);
        } catch (Throwable $e) {
            Log::warning('Failed reading SSR fallback metrics for retention cleanup.', [
                'exception' => $e,
                'disk' => $disk,
                'path' => $path,
            ]);

            return;
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $cutoff = $reference->copy()->subDays($days);
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $decoded = null;
            $timestamp = null;

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $timestamp = $this->extractJsonlTimestamp($decoded);
                }
            } catch (Throwable) {
                // Keep malformed lines to avoid data loss.
            }

            if ($timestamp !== null && $timestamp->lt($cutoff)) {
                continue;
            }

            if (is_array($decoded)) {
                try {
                    $kept[] = json_encode($decoded, JSON_THROW_ON_ERROR);

                    continue;
                } catch (Throwable) {
                    // Fall back to the original line.
                }
            }

            $kept[] = $trimmed;
        }

        if ($kept === []) {
            $filesystem->delete($path);

            return;
        }

        $filesystem->put($path, implode(PHP_EOL, $kept).PHP_EOL);
    }

    private function databaseTimestampColumn(): ?string
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            return 'collected_at';
        }

        if (Schema::hasColumn('ssr_metrics', 'created_at')) {
            return 'created_at';
        }

        if (Schema::hasColumn('ssr_metrics', 'updated_at')) {
            return 'updated_at';
        }

        return null;
    }

    private function ensureDirectoryExists(string $disk, string $path): void
    {
        $directory = Str::beforeLast($path, '/');

        if ($directory === $path || $directory === '' || $directory === '.') {
            return;
        }

        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($directory)) {
            $filesystem->makeDirectory($directory);
        }
    }

    private function extractJsonlTimestamp(array $payload): ?CarbonInterface
    {
        foreach (['ts', 'timestamp', 'collected_at', 'created_at'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $parsed = $this->attemptParseTimestamp($payload[$key]);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
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

    private function resolveTimestamp(mixed $value): CarbonInterface
    {
        return $this->attemptParseTimestamp($value) ?? Carbon::now();
    }

    private function attemptParseTimestamp(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::createFromInterface($value);
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
