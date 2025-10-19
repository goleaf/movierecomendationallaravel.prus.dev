<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\SsrMetricsFallbackStore;
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
    public function __construct(private SsrMetricsFallbackStore $fallbackStore) {}

    public function capture(Request $request, Response $response, float $startedAt): ?array
    {
        if (! $this->shouldCapture($request, $response)) {
            return null;
        }

        $path = $this->normalizePath($request->path());
        $firstByteMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));
        $metrics = $this->parseResponse($response);

        return $this->buildPayload($path, $firstByteMs, $metrics);
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
     *     collected_at: CarbonInterface|\DateTimeInterface|string|int|null,
     * }
     */
    public function normalizePayload(array $payload): array
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
            'collected_at' => $collectedAtSource,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeMetric(array $payload): void
    {
        $normalizedPayload = $this->normalizePayload($payload);

        $this->storeNormalizedMetric($normalizedPayload, $payload);
    }

    /**
     * @param  array{collected_at: CarbonInterface|\DateTimeInterface|string|int|null}  $normalizedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    public function storeNormalizedMetric(array $normalizedPayload, array $originalPayload = []): void
    {
        $normalizedPayload = $this->ensureNormalizedPayload($normalizedPayload);

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
    private function ensureNormalizedPayload(array $normalizedPayload): array
    {
        $collectedAt = Arr::get($normalizedPayload, 'collected_at');

        if (! $collectedAt instanceof CarbonInterface) {
            if ($collectedAt instanceof \DateTimeInterface) {
                $collectedAt = Carbon::createFromInterface($collectedAt);
            } elseif (is_numeric($collectedAt)) {
                $collectedAt = Carbon::createFromTimestamp((int) $collectedAt);
            } elseif (is_string($collectedAt) && $collectedAt !== '') {
                try {
                    $collectedAt = Carbon::parse($collectedAt);
                } catch (Throwable) {
                    $collectedAt = Carbon::now();
                }
            } else {
                $collectedAt = Carbon::now();
            }
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
        try {
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
        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            return 'collected_at';
        }

        if (Schema::hasColumn('ssr_metrics', 'created_at')) {
            return 'created_at';
        }

        return 'updated_at';
    }

    private function shouldCapture(Request $request, Response $response): bool
    {
        if (! config('ssrmetrics.enabled')) {
            return false;
        }

        $path = $this->normalizePath($request->path());
        $monitoredPaths = collect(config('ssrmetrics.paths', []))
            ->map(fn ($configuredPath) => $this->normalizePath((string) $configuredPath));

        if (! $monitoredPaths->contains($path)) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($contentType === '' || ! Str::contains($contentType, 'text/html')) {
            return false;
        }

        return true;
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

    private function resolveFallbackTimestamp(array $record): ?CarbonInterface
    {
        $value = $record['ts']
            ?? $record['collected_at']
            ?? $record['timestamp']
            ?? null;

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
