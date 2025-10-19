<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = device_id();
        $request->attributes->set('device_id', $deviceId);

        $variant = $this->resolveVariant($request);
        if ($variant !== null) {
            $request->attributes->set('ab_variant', $variant);
        }

        $context = array_filter([
            'device_id' => $deviceId,
            'ab_variant' => $variant,
        ], static fn ($value) => $value !== null);

        if ($context !== []) {
            Log::withContext($context);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
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
