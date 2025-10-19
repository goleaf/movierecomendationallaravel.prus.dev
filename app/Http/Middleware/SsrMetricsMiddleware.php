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
    public function __construct(private readonly SsrMetricsService $ssrMetricsService)
    {
    }

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

        $metrics = $this->ssrMetricsService->parseResponse($response);
        $score = $this->ssrMetricsService->computeScore($metrics);
        $payload = $this->ssrMetricsService->buildPayload($path, $firstByteMs, $metrics, $score);

        StoreSsrMetric::dispatch($payload);

        return $response;
    }
}
