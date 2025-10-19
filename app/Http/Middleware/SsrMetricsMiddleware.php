<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SsrMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsMiddleware
{
    public function __construct(private readonly SsrMetricsService $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $this->metrics->capture($request, $response, $startedAt);

        return $response;
    }
}
