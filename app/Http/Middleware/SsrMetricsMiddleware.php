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
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'has_json_ld' => $ld > 0,
            'has_open_graph' => $og > 0,
        ];

        $score = 100;
        $scoringConfig = config('ssrmetrics.scoring', []);
        $weights = $scoringConfig['weights'] ?? [];
        $thresholds = $scoringConfig['thresholds'] ?? [];

        $blockingWeight = max(0, (int) ($weights['blocking_scripts'] ?? 5));
        $blockingCap = max(0, (int) ($weights['blocking_cap'] ?? 30));
        $ldjsonPenalty = max(0, (int) ($weights['ldjson_missing'] ?? 10));
        $opengraphPenalty = max(0, (int) ($weights['opengraph_insufficient'] ?? 10));
        $htmlPenalty = max(0, (int) ($weights['oversized_html'] ?? 20));
        $imagesPenalty = max(0, (int) ($weights['image_overflow'] ?? 10));

        $opengraphMinimum = max(0, (int) ($thresholds['opengraph_minimum'] ?? 3));
        $maxHtmlBytes = max(0, (int) ($thresholds['max_html_bytes'] ?? (900 * 1024)));
        $maxImages = max(0, (int) ($thresholds['max_images'] ?? 60));

        if ($blocking > 0 && $blockingWeight > 0) {
            $score -= min($blockingCap, $blockingWeight * $blocking);
        }

        if ($ld === 0) {
            $score -= $ldjsonPenalty;
        }

        if ($og < $opengraphMinimum) {
            $score -= $opengraphPenalty;
        }

        if ($maxHtmlBytes > 0 && $size > $maxHtmlBytes) {
            $score -= $htmlPenalty;
        }

        if ($maxImages > 0 && $imgs > $maxImages) {
            $score -= $imagesPenalty;
        }

        $score = max(0, min(100, $score));

        $payload = [
            'path' => $path,
            'score' => $score,
            'html_size' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'first_byte_ms' => $firstByteMs,
            'meta' => $metaPayload,
        ];

        StoreSsrMetric::dispatch($payload);

        return $response;
    }
}
