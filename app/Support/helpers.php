<?php

declare(strict_types=1);

use App\Support\ImageProxyStorage;
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
    function proxy_image_url(?string $url, ?bool $refresh = null): ?string
    {
        $normalized = ImageProxyStorage::normalizeUrl($url);

        if ($normalized === null) {
            return null;
        }

        $parameters = [
            'hash' => ImageProxyStorage::hash($normalized),
            'url' => base64_encode($normalized),
        ];

        if (ImageProxyStorage::shouldRefresh($refresh)) {
            $parameters['refresh'] = 1;
            $parameters['t'] = now()->timestamp;
        }

        return URL::signedRoute('image-proxy.show', $parameters);
    }
}
