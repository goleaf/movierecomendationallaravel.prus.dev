<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsService
{
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
                'recorded_at' => now()->toIso8601String(),
                'first_byte_ms' => $firstByteMs,
                'meta' => $metaPayload,
            ],
            $metrics,
        );
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
}
