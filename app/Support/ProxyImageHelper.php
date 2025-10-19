<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

final class ProxyImageHelper
{
    public static function hashFor(string $url): string
    {
        return hash('sha256', $url);
    }

    public static function encodeSource(string $url): string
    {
        $encoded = base64_encode($url);

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    public static function decodeSource(string $encoded): string
    {
        $encoded = strtr($encoded, '-_', '+/');
        $padding = strlen($encoded) % 4;

        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid proxied image payload.');
        }

        return $decoded;
    }

    /**
     * @return array{hash: string, source: string}
     */
    public static function routeParameters(string $url): array
    {
        return [
            'hash' => self::hashFor($url),
            'source' => self::encodeSource($url),
        ];
    }

    public static function signedUrl(string $url): string
    {
        return URL::signedRoute('image-proxy.show', self::routeParameters($url));
    }
}
