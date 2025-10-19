<?php

declare(strict_types=1);

use App\Support\Storage\ProxyImageUrlGenerator;
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

if (! function_exists('proxy_image_url')) {
    function proxy_image_url(?string $source): ?string
    {
        if (! is_string($source)) {
            return null;
        }

        $trimmed = trim($source);

        if ($trimmed === '') {
            return null;
        }

        if (! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (Str::startsWith($trimmed, url('/images/proxy'))) {
            return $trimmed;
        }

        return app(ProxyImageUrlGenerator::class)->generate($trimmed);
    }
}
