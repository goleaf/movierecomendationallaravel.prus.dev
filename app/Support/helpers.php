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

if (! function_exists('image_proxy_url')) {
    function image_proxy_url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return $url;
        }

        $router = app('router');
        if (! $router->has('images.proxy')) {
            return $url;
        }

        $appUrl = (string) config('app.url', '');
        $appHost = $appUrl !== '' ? parse_url($appUrl, PHP_URL_HOST) : null;
        $host = parse_url($url, PHP_URL_HOST);

        if ($appHost !== null && $host !== null && strcasecmp($host, $appHost) === 0) {
            return $url;
        }

        return URL::temporarySignedRoute('images.proxy', now()->addMinutes(30), ['url' => $url]);
    }
}
