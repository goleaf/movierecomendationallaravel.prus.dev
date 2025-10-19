<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\StoreSsrMetric;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! config('ssrmetrics.enabled')) {
            return $response;
        }

        $path = '/'.ltrim($request->path(), '/');

        if (! collect(config('ssrmetrics.paths', []))->contains($path)) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($contentType === '' || ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $firstByteMs = (int) round((microtime(true) - $startedAt) * 1000);

        $html = $response->getContent() ?? '';
        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $metaPayload = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $size,
            'html_bytes' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'has_json_ld' => $ld > 0,
            'has_open_graph' => $og > 0,
        ];

        $score = 100;

        if ($blocking > 0) {
            $perScriptPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.per_script', 5);
            $maxPenalty = (int) config('ssrmetrics.penalties.blocking_scripts.max', 30);

            $score -= min($maxPenalty, $perScriptPenalty * $blocking);
        }

        if ($ld === 0) {
            $score -= (int) config('ssrmetrics.penalties.missing_ldjson.deduction', 10);
        }

        $minimumOgTags = (int) config('ssrmetrics.penalties.low_og.minimum', 3);

        if ($og < $minimumOgTags) {
            $score -= (int) config('ssrmetrics.penalties.low_og.deduction', 10);
        }

        $oversizedThreshold = (int) config('ssrmetrics.penalties.oversized_html.threshold', 900 * 1024);

        if ($size > $oversizedThreshold) {
            $score -= (int) config('ssrmetrics.penalties.oversized_html.deduction', 20);
        }

        $excessImageThreshold = (int) config('ssrmetrics.penalties.excess_images.threshold', 60);

        if ($imgs > $excessImageThreshold) {
            $score -= (int) config('ssrmetrics.penalties.excess_images.deduction', 10);
        }

        $score = max(0, $score);

        $payload = [
            'path' => $path,
            'score' => $score,
            'collected_at' => now()->toIso8601String(),
            'html_bytes' => $size,
            'html_size' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'first_byte_ms' => $firstByteMs,
            'meta' => $metaPayload,
            'has_json_ld' => $ld > 0,
            'has_open_graph' => $og > 0,
        ];

        StoreSsrMetric::dispatch($payload);

        return $response;
    }
}
