<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
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
        if (blank($url)) {
            return null;
        }

        return URL::signedRoute('api.artwork', ['src' => $url]);
    }
}

if (! function_exists('poster_image_url')) {
    function poster_image_url(?string $url, ?string $fallback = null): string
    {
        if (blank($url)) {
            if (! blank($fallback)) {
                return Str::startsWith($fallback, ['http://', 'https://', 'data:'])
                    ? $fallback
                    : asset(ltrim($fallback, '/'));
            }

            return asset('img/og-default.svg');
        }

        $parameters = ['src' => $url];

        if (! blank($fallback) && ! Str::startsWith($fallback, ['http://', 'https://', 'data:'])) {
            $parameters['fallback'] = ltrim($fallback, '/');
        }

        return URL::signedRoute('media.poster', $parameters);
    }
}

if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        if (function_exists('app') && app()->bound('csp-nonce')) {
            return (string) app('csp-nonce');
        }

        return '';
    }
}
