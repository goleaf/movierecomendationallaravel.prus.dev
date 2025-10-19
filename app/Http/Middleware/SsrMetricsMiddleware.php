<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\StoreSsrMetric;
use App\Services\SsrMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsMiddleware
{
    public function __construct(private readonly SsrMetricsService $metricsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! $this->metricsService->isEnabled()) {
            return $response;
        }

        $path = $this->metricsService->normalizePath($request->path());

        if (! $this->metricsService->shouldTrackPath($path)) {
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

        $score = $this->metricsService->computeScore([
            'blocking_scripts' => $blocking,
            'ldjson_count' => $ld,
            'og_count' => $og,
            'html_size' => $size,
            'img_count' => $imgs,
        ]);

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
