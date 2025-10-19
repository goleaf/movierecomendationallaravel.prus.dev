<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageProxyStorage
{
    private const DISK = 'public';

    private const BASE_DIRECTORY = 'image-proxy';

    private const REFRESH_AFTER_MINUTES = 1440;

    /**
     * @var array<string, string>
     */
    private const EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    public static function normalizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://')) {
            if (str_starts_with($url, '//')) {
                $url = 'https:'.$url;
            } else {
                $url = 'https://'.$url;
            }
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);

        $port = $parts['port'] ?? null;
        if ($port !== null) {
            $port = (int) $port;

            if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
                $port = null;
            }
        }

        $path = $parts['path'] ?? '';

        if ($path === '') {
            $path = '/';
        } else {
            $segments = explode('/', $path);

            $segments = array_map(static function (string $segment): string {
                $decoded = rawurldecode($segment);

                return rawurlencode($decoded);
            }, $segments);

            $path = implode('/', $segments);

            if (! str_starts_with($path, '/')) {
                $path = '/'.$path;
            }
        }

        $query = '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $pairs = explode('&', $parts['query']);

            $normalizedPairs = [];

            foreach ($pairs as $pair) {
                if ($pair === '') {
                    continue;
                }

                $kv = explode('=', $pair, 2);
                $key = rawurldecode($kv[0]);
                $value = isset($kv[1]) ? rawurldecode($kv[1]) : null;
                $normalizedPairs[] = [$key, $value];
            }

            if ($normalizedPairs !== []) {
                usort($normalizedPairs, static function (array $a, array $b): int {
                    $keyComparison = $a[0] <=> $b[0];

                    if ($keyComparison !== 0) {
                        return $keyComparison;
                    }

                    return ($a[1] ?? '') <=> ($b[1] ?? '');
                });

                $query = implode('&', array_map(static function (array $pair): string {
                    [$key, $value] = $pair;

                    $encodedKey = rawurlencode($key);

                    if ($value === null) {
                        return $encodedKey;
                    }

                    return $encodedKey.'='.rawurlencode($value);
                }, $normalizedPairs));
            }
        }

        $normalized = "{$scheme}://{$host}";

        if ($port !== null) {
            $normalized .= ':'.$port;
        }

        $normalized .= $path;

        if ($query !== '') {
            $normalized .= '?'.$query;
        }

        return $normalized;
    }

    public static function cacheKey(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public static function directory(string $key): string
    {
        return self::BASE_DIRECTORY.'/'.substr($key, 0, 2).'/'.$key;
    }

    public static function imagePath(string $key, ?string $extension = null): string
    {
        $suffix = $extension !== null && $extension !== '' ? '.'.$extension : '';

        return self::directory($key).'/image'.$suffix;
    }

    public static function metadataPath(string $key): string
    {
        return self::directory($key).'/meta.json';
    }

    public static function ensureDirectory(string $key): void
    {
        $disk = self::disk();
        $disk->makeDirectory(self::BASE_DIRECTORY);
        $disk->makeDirectory(self::directory($key));
    }

    public static function readMetadata(string $key): ?array
    {
        $path = self::metadataPath($key);

        if (! self::disk()->exists($path)) {
            return null;
        }

        $contents = self::disk()->get($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public static function writeMetadata(string $key, array $metadata): void
    {
        self::ensureDirectory($key);

        self::disk()->put(
            self::metadataPath($key),
            json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function deleteExisting(string $key, ?string $excludePath = null): void
    {
        $metadata = self::readMetadata($key);

        if ($metadata === null) {
            return;
        }

        $existingPath = $metadata['path'] ?? null;

        if (! is_string($existingPath) || $existingPath === '' || $existingPath === $excludePath) {
            return;
        }

        if (self::disk()->exists($existingPath)) {
            self::disk()->delete($existingPath);
        }
    }

    public static function storedPath(string $key, ?array $metadata = null): ?string
    {
        $metadata ??= self::readMetadata($key);

        if ($metadata === null) {
            return null;
        }

        $path = $metadata['path'] ?? null;

        if (! is_string($path) || $path === '' || ! self::disk()->exists($path)) {
            return null;
        }

        return $path;
    }

    public static function shouldRefresh(string $key, ?array $metadata = null): bool
    {
        $metadata ??= self::readMetadata($key);

        if ($metadata === null) {
            return true;
        }

        $path = $metadata['path'] ?? null;

        if (! is_string($path) || $path === '' || ! self::disk()->exists($path)) {
            return true;
        }

        $cachedAt = self::parseCachedAt($metadata['cached_at'] ?? null);

        if ($cachedAt === null) {
            return true;
        }

        return $cachedAt->addMinutes(self::REFRESH_AFTER_MINUTES)->isPast();
    }

    public static function extensionFromContentType(?string $contentType): ?string
    {
        if (! is_string($contentType)) {
            return null;
        }

        $normalized = strtolower(trim($contentType));

        return self::EXTENSION_MAP[$normalized] ?? null;
    }

    public static function extensionFromUrl(string $normalizedUrl): ?string
    {
        $path = parse_url($normalizedUrl, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '' || strlen($extension) > 5) {
            return null;
        }

        return $extension;
    }

    private static function parseCachedAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
