<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-ID', $requestId);

        Log::withContext([
            'request_id' => $requestId,
        ]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-ID', $request->headers->get('X-Request-Id'));

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::orderedUuid();
    }
}
