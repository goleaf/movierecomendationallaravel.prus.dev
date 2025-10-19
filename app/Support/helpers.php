<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

if (! function_exists('device_id')) {
    function device_id(): string
    {
        $key = 'did';
        $cookieValue = request()->cookie($key);

        $deviceId = null;

        if (is_string($cookieValue)) {
            $trimmedValue = trim($cookieValue);

            if ($trimmedValue !== '') {
                $deviceId = $trimmedValue;
            }
        } elseif (is_array($cookieValue)) {
            $firstValue = reset($cookieValue);

            if (is_string($firstValue)) {
                $trimmedValue = trim($firstValue);

                if ($trimmedValue !== '') {
                    $deviceId = $trimmedValue;
                }
            }
        }

        if (! is_string($deviceId)) {
            $deviceId = 'd_'.Str::uuid()->toString();

            Cookie::queue(Cookie::make($key, $deviceId, 60 * 24 * 365 * 5));
        }

        return $deviceId;
    }
}
