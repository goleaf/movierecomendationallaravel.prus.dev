<?php

declare(strict_types=1);

use App\Support\ProxyImageHelper;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

if (! function_exists('device_id')) {
    function device_id(): string
    {
        $key = 'did';
        $did = request()->cookie($key);
        if (! $did) {
            $did = 'd_'.Str::uuid()->toString();
            Cookie::queue(Cookie::make($key, $did, 60 * 24 * 365 * 5));
        }

        return $did;
    }
}

if (! function_exists('proxy_image')) {
    function proxy_image(string $sourceUrl): string
    {
        return ProxyImageHelper::signedUrl($sourceUrl);
    }
}
