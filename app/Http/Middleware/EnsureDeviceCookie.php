<?php

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
