<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsService
{
    public function shouldCapture(Request $request, Response $response): bool
    {
        if (! config('ssrmetrics.enabled')) {
            return false;
        }

        $path = $this->normalizePath($request->path());

        $paths = collect(config('ssrmetrics.paths', []))
            ->map(function (mixed $configured): string {
                return $this->normalizePath(is_string($configured) ? $configured : (string) $configured);
            })
            ->filter()
            ->unique();

        if (! $paths->contains($path)) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType === '') {
            return false;
        }

        return Str::contains(strtolower($contentType), 'text/html');
    }

    public function buildPayload(Request $request, Response $response, float $startedAt): array
    {
        $path = $this->normalizePath($request->path());
        $html = (string) ($response->getContent() ?? '');

        $firstByteMs = (int) round((microtime(true) - $startedAt) * 1000);

        $size = strlen($html);
        $metaCount = preg_match_all('/<meta\b[^>]*>/i', $html);
        $ogCount = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ldjsonCount = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgCount = preg_match_all('/<img\b[^>]*>/i', $html);
        $blockingScripts = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $meta = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $size,
            'html_bytes' => $size,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
        ];

        $score = $this->calculateScore($size, $metaCount, $ogCount, $ldjsonCount, $imgCount, $blockingScripts);

        return [
            'path' => $path,
            'score' => $score,
            'collected_at' => Carbon::now()->toIso8601String(),
            'html_bytes' => $size,
            'html_size' => $size,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'first_byte_ms' => $firstByteMs,
            'meta' => $meta,
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function storageOrder(): array
    {
        $order = config('ssrmetrics.storage.order', ['database', 'jsonl']);

        if (! is_array($order)) {
            $order = [$order];
        }

        return collect($order)
            ->map(fn ($driver): string => (string) $driver)
            ->filter()
            ->filter(fn (string $driver): bool => $this->storageEnabled($driver))
            ->values()
            ->all();
    }

    public function storageEnabled(string $driver): bool
    {
        return (bool) ($this->storageConfig($driver)['enabled'] ?? true);
    }

    public function databaseRetentionDays(): ?int
    {
        return $this->normalizeRetention($this->storageConfig('database')['retention_days'] ?? null);
    }

    public function jsonlRetentionDays(): ?int
    {
        return $this->normalizeRetention($this->storageConfig('jsonl')['retention_days'] ?? null);
    }

    public function jsonlDisk(): string
    {
        $disk = $this->storageConfig('jsonl')['disk'] ?? config('filesystems.default', 'local');

        $disk = is_string($disk) && $disk !== '' ? $disk : 'local';

        return $disk;
    }

    public function jsonlPath(): string
    {
        $path = $this->storageConfig('jsonl')['path'] ?? 'metrics/ssr.jsonl';
        $path = is_string($path) && $path !== '' ? $path : 'metrics/ssr.jsonl';

        return ltrim($path, '/');
    }

    private function calculateScore(int $size, int $metaCount, int $ogCount, int $ldjsonCount, int $imgCount, int $blockingScripts): int
    {
        $score = 100;

        if ($blockingScripts > 0) {
            $perScriptPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.per_script', 5);
            $maxPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.max', 30);

            $score -= min($maxPenalty, $perScriptPenalty * $blockingScripts);
        }

        if ($ldjsonCount === 0) {
            $score -= (int) config('ssrmetrics.penalties.missing_ldjson.deduction', 10);
        }

        $minimumOgTags = (int) config('ssrmetrics.penalties.low_og.minimum', 3);

        if ($ogCount < $minimumOgTags) {
            $score -= (int) config('ssrmetrics.penalties.low_og.deduction', 10);
        }

        $oversizedThreshold = (int) config('ssrmetrics.penalties.oversized_html.threshold', 900 * 1024);

        if ($size > $oversizedThreshold) {
            $score -= (int) config('ssrmetrics.penalties.oversized_html.deduction', 20);
        }

        $excessImageThreshold = (int) config('ssrmetrics.penalties.excess_images.threshold', 60);

        if ($imgCount > $excessImageThreshold) {
            $score -= (int) config('ssrmetrics.penalties.excess_images.deduction', 10);
        }

        return max(0, $score);
    }

    private function normalizePath(?string $path): string
    {
        $path = $path ?? '/';
        $path = '/'.ltrim($path, '/');

        return $path !== '' ? $path : '/';
    }

    private function storageConfig(string $driver): array
    {
        $config = config("ssrmetrics.storage.$driver", []);

        return is_array($config) ? $config : [];
    }

    private function normalizeRetention(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && $value === '') {
            return null;
        }

        $days = (int) $value;

        return $days > 0 ? $days : null;
    }
}
