<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
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

if (! function_exists('proxy_image_url')) {
    function proxy_image_url(?string $url, string $kind = 'poster', ?Carbon $expires = null): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $kind = in_array($kind, ['poster', 'backdrop'], true) ? $kind : 'poster';

        $encodedUrl = base64_encode($url);

        $ttl = config('services.artwork_proxy.ttl');
        $expiration = $expires;

        if ($expiration === null && is_numeric($ttl) && (int) $ttl > 0) {
            $expiration = Carbon::now()->addSeconds((int) $ttl);
        }

        return URL::signedRoute('proxy.image', [
            'url' => $encodedUrl,
            'kind' => $kind,
        ], $expiration);
    }
}
