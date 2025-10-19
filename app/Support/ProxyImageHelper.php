<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class ProxyImageHelper
{
    private const BASE_DIRECTORY = 'proxied-images';

    public static function diskName(): string
    {
        return config('filesystems.proxy_image_disk', 'proxy_images');
    }

    public static function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Image URL cannot be empty.');
        }

        if (! str_contains($trimmed, '://')) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);

        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid image URL provided.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP(S) image URLs are supported.');
        }

        $host = strtolower($parts['host']);

        if (! str_contains($host, '.')) {
            throw new InvalidArgumentException('Image host must be a fully-qualified domain.');
        }
        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? null;

        $normalized = $scheme.'://'.$host;

        if ($port !== null && ! self::isDefaultPort($scheme, (int) $port)) {
            $normalized .= ':'.$port;
        }

        if ($path !== '') {
            $normalized .= $path;
        }

        if ($query !== null && $query !== '') {
            $normalized .= '?'.$query;
        }

        return $normalized;
    }

    public static function basePath(string $normalizedUrl): string
    {
        $hash = hash('sha256', $normalizedUrl);

        return implode('/', [
            self::BASE_DIRECTORY,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
        ]);
    }

    public static function contentPath(string $normalizedUrl): string
    {
        return self::basePath($normalizedUrl).'/image';
    }

    public static function metadataPath(string $normalizedUrl): string
    {
        return self::basePath($normalizedUrl).'/meta.json';
    }

    public static function signedUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        try {
            $normalized = self::normalizeUrl($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        return URL::signedRoute('images.proxy', ['url' => $normalized]);
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }
}
