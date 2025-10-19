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
    public function __construct(private readonly SsrMetricsService $ssrMetricsService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! $this->ssrMetricsService->shouldCapture($request, $response)) {
            return $response;
        }

        $payload = $this->ssrMetricsService->makePayload($request, $response, $startedAt);

        if ($payload !== []) {
            StoreSsrMetric::dispatch($payload);
        }

        return $response;
    }
}
