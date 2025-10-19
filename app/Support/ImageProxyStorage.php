<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageProxyStorage
{
    private const CACHE_DIRECTORY = 'image-proxy';

    private const DEFAULT_EXTENSION = 'jpg';

    private const REFRESH_FLAG_QUERY = 'refresh_proxy_images';

    public static function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function normalizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '//')) {
            $trimmed = 'https:'.$trimmed;
        }

        if (! str_contains($trimmed, '://')) {
            $trimmed = 'https://'.$trimmed;
        }

        if (! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $trimmed;
    }

    public static function hash(string $normalizedUrl): string
    {
        return hash('sha1', $normalizedUrl);
    }

    public static function extension(string $normalizedUrl): string
    {
        $path = parse_url($normalizedUrl, PHP_URL_PATH);
        $extension = $path ? pathinfo($path, PATHINFO_EXTENSION) : null;

        if (! is_string($extension) || $extension === '') {
            return self::DEFAULT_EXTENSION;
        }

        $extension = Str::lower($extension);

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            return self::DEFAULT_EXTENSION;
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    public static function relativePath(string $normalizedUrl): string
    {
        $hash = self::hash($normalizedUrl);
        $extension = self::extension($normalizedUrl);
        $segments = [
            self::CACHE_DIRECTORY,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash.'.'.$extension,
        ];

        return implode('/', $segments);
    }

    public static function shouldRefresh(?bool $explicit = null): bool
    {
        if ($explicit !== null) {
            return $explicit;
        }

        $request = request();

        if ($request === null) {
            return false;
        }

        return $request->boolean(self::REFRESH_FLAG_QUERY);
    }

    public static function forget(string $normalizedUrl): void
    {
        $path = self::relativePath($normalizedUrl);
        self::disk()->delete($path);
    }
}
