<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Csp\AddCspHeaders;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders extends AddCspHeaders
{
    public function handle(Request $request, Closure $next, ?string $customPreset = null)
    {
        $response = parent::handle($request, $next, $customPreset);

        if ($response instanceof Response) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
