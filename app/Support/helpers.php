<?php

declare(strict_types=1);

use App\Jobs\CacheProxyImage;
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
    function proxy_image_url(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        /** @var ImageProxyStorage $storage */
        $storage = app(ImageProxyStorage::class);

        $normalized = $storage->normalizeUrl($url);
        $cacheKey = $storage->cacheKeyFor($normalized);

        CacheProxyImage::dispatch($normalized, $cacheKey);

        return URL::signedRoute('proxy.image', [
            'cacheKey' => $cacheKey,
            'source' => $normalized,
        ]);
    }
}
