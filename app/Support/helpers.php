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
    function proxy_image_url(?string $url, int $ttlSeconds = 3600): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $normalized = ImageProxyStorage::normalizeUrl($url);

        if ($normalized === null) {
            return null;
        }

        $key = ImageProxyStorage::cacheKey($normalized);
        $metadata = ImageProxyStorage::readMetadata($key);

        if (ImageProxyStorage::shouldRefresh($key, $metadata)) {
            CacheProxyImage::dispatch($normalized);
        }

        $expiresAt = now()->addSeconds(max(60, $ttlSeconds));

        return URL::temporarySignedRoute('images.proxy', $expiresAt, [
            'key' => $key,
            'url' => base64_encode($normalized),
        ]);
    }
}
