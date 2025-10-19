<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

if (!function_exists('device_id')) {
    function device_id(): string {
        $key='did'; $did=request()->cookie($key);
        if (!$did) { $did='d_'.Str::uuid()->toString(); Cookie::queue(Cookie::make($key,$did,60*24*365*5)); }
        return $did;
    }
}
