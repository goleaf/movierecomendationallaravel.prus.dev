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

if (! function_exists('proxy_image_url')) {
    function proxy_image_url(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            return null;
        }

        if (Str::contains($trimmed, '/media/proxy') && str_contains($trimmed, 'signature=')) {
            return $trimmed;
        }

        $encoded = rtrim(strtr(base64_encode($trimmed), '+/', '-_'), '=');

        return URL::signedRoute('media.proxy', ['u' => $encoded]);
    }
}
