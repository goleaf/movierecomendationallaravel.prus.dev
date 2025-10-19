<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-ID', $requestId);

        $deviceId = device_id();
        $request->attributes->set('device_id', $deviceId);

        $variant = $this->resolveVariant($request);
        if ($variant !== null) {
            $request->attributes->set('ab_variant', $variant);
        }

        Log::withContext(array_filter([
            'request_id' => $requestId,
            'device_id' => $deviceId,
            'ab_variant' => $variant,
        ]));

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    protected function resolveRequestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-ID', $request->headers->get('X-Request-Id'));

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::orderedUuid();
    }

    protected function resolveVariant(Request $request): ?string
    {
        $variant = $request->attributes->get('ab_variant');
        if (is_string($variant) && $variant !== '') {
            return $variant;
        }

        $cookie = $request->cookie('ab_variant');
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        return null;
    }
}
