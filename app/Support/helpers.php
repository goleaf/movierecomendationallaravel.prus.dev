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
        if (blank($url)) {
            return null;
        }

        if (Str::startsWith($url, ['/proxy/', url('/proxy/')])) {
            return $url;
        }

        $encoded = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        return URL::signedRoute('proxy.image', ['encoded' => $encoded]);
    }
}

if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        if (app()->bound('csp-nonce')) {
            return (string) app('csp-nonce');
        }

        return '';
    }
}
