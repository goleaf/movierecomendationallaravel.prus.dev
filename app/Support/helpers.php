<?php

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

if (! function_exists('device_id')) {
    function device_id(): string
    {
        $key = 'did';
        $deviceId = request()->cookie($key);

        if (is_string($deviceId) && $deviceId !== '') {
            return $deviceId;
        }

        $deviceId = 'd_'.Str::uuid()->toString();

        Cookie::queue(Cookie::make($key, $deviceId, 60 * 24 * 365 * 5));

        return $deviceId;
    }
}
