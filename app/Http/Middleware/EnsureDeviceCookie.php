<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDeviceCookie
{
    public function handle(Request $request, Closure $next)
    {
        device_id();
        return $next($request);
    }
}
