<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ImageProxyStorage
{
    private const DIRECTORY = 'image-proxy';

    private const META_SUFFIX = '.json';

    private const MAX_FILE_SIZE = 7_340_032; // ~7MB safety limit

    /**
     * @return array{cache_path: string, meta: array<string, mixed>}|null
     */
    public static function refresh(string $url): ?array
    {
        $download = self::download($url);

        if ($download === null) {
            return null;
        }

        $disk = self::disk();
        self::ensureDirectory($disk);

        $cachePath = self::cachePath($url);
        $disk->put($cachePath, $download['body']);

        $meta = [
            'source' => $url,
            'content_type' => $download['content_type'],
            'content_length' => $download['content_length'],
            'fetched_at' => now()->toIso8601String(),
        ];

        self::writeMeta($disk, $cachePath, $meta);

        return [
            'cache_path' => $cachePath,
            'meta' => $meta,
        ];
    }

    public static function cachePath(string $url): string
    {
        return self::DIRECTORY.'/'.hash('sha256', $url);
    }

    public static function metaPath(string $cachePath): string
    {
        return $cachePath.self::META_SUFFIX;
    }

    public static function exists(string $cachePath): bool
    {
        return self::disk()->exists($cachePath);
    }

    /**
     * @return array<string, mixed>
     */
    public static function readMeta(string $cachePath): array
    {
        $disk = self::disk();
        $metaPath = self::metaPath($cachePath);

        if (! $disk->exists($metaPath)) {
            return [];
        }

        $contents = $disk->get($metaPath);

        if ($contents === false || $contents === '') {
            return [];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (Throwable) {
            return [];
        }
    }

    public static function lastModified(string $cachePath): ?CarbonImmutable
    {
        $disk = self::disk();

        if (! $disk->exists($cachePath)) {
            return null;
        }

        $timestamp = $disk->lastModified($cachePath);

        return $timestamp > 0 ? CarbonImmutable::createFromTimestampUTC($timestamp) : null;
    }

    public static function mimeType(string $cachePath, array $meta = []): string
    {
        $contentType = (string) Arr::get($meta, 'content_type', '');

        if ($contentType !== '') {
            return $contentType;
        }

        $disk = self::disk();
        $detected = $disk->mimeType($cachePath);

        return $detected ?? 'application/octet-stream';
    }

    public static function size(string $cachePath, array $meta = []): int
    {
        $length = Arr::get($meta, 'content_length');

        if (is_int($length) && $length >= 0) {
            return $length;
        }

        return self::disk()->size($cachePath);
    }

    public static function path(string $cachePath): string
    {
        return self::disk()->path($cachePath);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function streamedResponse(string $cachePath, array $headers): StreamedResponse
    {
        return self::disk()->response($cachePath, null, $headers);
    }

    private static function disk(): FilesystemAdapter
    {
        return Storage::disk('public');
    }

    private static function ensureDirectory(FilesystemAdapter $disk): void
    {
        if (! $disk->exists(self::DIRECTORY)) {
            $disk->makeDirectory(self::DIRECTORY);
        }
    }

    /**
     * @return array{body: string, content_type: string, content_length: int}|null
     */
    private static function download(string $url): ?array
    {
        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->withUserAgent('MovieRecProxy/1.0')
                ->accept('*/*')
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '') {
            return null;
        }

        $length = strlen($body);

        if ($length > self::MAX_FILE_SIZE) {
            return null;
        }

        $contentType = (string) $response->header('Content-Type', 'application/octet-stream');
        $normalizedType = trim(Str::lower(Str::before($contentType, ';')));

        if ($normalizedType === '') {
            $normalizedType = 'application/octet-stream';
        }

        return [
            'body' => $body,
            'content_type' => $normalizedType,
            'content_length' => $length,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function writeMeta(FilesystemAdapter $disk, string $cachePath, array $meta): void
    {
        $disk->put(self::metaPath($cachePath), json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
