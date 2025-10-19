<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

if (! function_exists('device_id')) {
    function device_id(): string
    {
        $key = 'did';
        $id = request()->cookie($key);

        if (! is_string($id) || $id === '') {
            $id = 'd_'.Str::uuid()->toString();
            Cookie::queue(Cookie::make($key, $id, 60 * 24 * 365 * 5));
        }

        return $id;
    }
}
