<?php

declare(strict_types=1);

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

if (! function_exists('artwork_url')) {
    function artwork_url(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $proxy = (string) config('services.artwork.proxy_url', '');

        if ($proxy !== '') {
            $normalizedProxy = rtrim($proxy, '&?/');

            if ($normalizedProxy !== '' && Str::startsWith($trimmed, $normalizedProxy)) {
                return $trimmed;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                $base = rtrim($proxy, '&?');
                $separator = str_contains($base, '?') ? '&' : '?';

                return $base.$separator.'url='.rawurlencode($trimmed);
            }
        }

        return $trimmed;
    }
}
