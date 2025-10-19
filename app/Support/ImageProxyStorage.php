<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImageProxyStorage
{
    public const DIRECTORY = 'proxy-artwork';

    public const METADATA_EXTENSION = 'json';

    public const STALE_AFTER_SECONDS = 86_400;

    /**
     * A soft limit to avoid storing unexpectedly large files.
     */
    public const MAX_BYTES = 5_242_880; // 5 MiB

    protected Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = Storage::disk('public');
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        try {
            $uri = new Uri($url);
        } catch (InvalidArgumentException) {
            return $url;
        }

        if ($uri->getScheme() !== '') {
            $uri = $uri->withScheme(Str::lower($uri->getScheme()));
        }

        if ($uri->getHost() !== '') {
            $uri = $uri->withHost(Str::lower($uri->getHost()));
        }

        $uri = $this->withoutDefaultPort($uri);

        if ($uri->getFragment() !== '') {
            $uri = $uri->withFragment('');
        }

        $path = $uri->getPath();
        if ($path === '') {
            $uri = $uri->withPath('/');
        }

        $query = $uri->getQuery();
        if ($query !== '') {
            parse_str($query, $params);
            ksort($params);
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $uri = $uri->withQuery($query);
        }

        return (string) $uri;
    }

    public function cacheKeyFor(string $url): string
    {
        return hash('sha256', $this->normalizeUrl($url));
    }

    public function extensionForMime(?string $mime): string
    {
        $mime = $mime ? Str::lower(trim(Str::before($mime, ';'))) : '';

        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];

        return $map[$mime] ?? 'bin';
    }

    public function pathFor(string $url): string
    {
        $cacheKey = $this->cacheKeyFor($url);

        return $this->pathForCacheKey($cacheKey);
    }

    public function pathForCacheKey(string $cacheKey, ?string $extension = null): string
    {
        $extension ??= Arr::get($this->metadata($cacheKey), 'extension', 'bin');

        return self::DIRECTORY.'/'.$cacheKey.'.'.$extension;
    }

    public function metadata(string $cacheKey): ?array
    {
        $path = $this->metadataPath($cacheKey);

        if (! $this->filesystem->exists($path)) {
            return null;
        }

        $contents = $this->filesystem->get($path);

        return json_decode($contents, true) ?: null;
    }

    public function putMetadata(string $cacheKey, array $metadata): void
    {
        $payload = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->filesystem->put($this->metadataPath($cacheKey), $payload);
    }

    public function metadataPath(string $cacheKey): string
    {
        return self::DIRECTORY.'/'.$cacheKey.'.'.self::METADATA_EXTENSION;
    }

    public function publicUrl(string $cacheKey): ?string
    {
        $path = $this->pathForCacheKey($cacheKey);

        if (! $this->filesystem->exists($path)) {
            return null;
        }

        return $this->filesystem->url($path);
    }

    public function isStale(?array $metadata): bool
    {
        $fetchedAt = Arr::get($metadata, 'fetched_at');

        if (blank($fetchedAt)) {
            return true;
        }

        $timestamp = Carbon::parse($fetchedAt);

        return $timestamp->addSeconds(self::STALE_AFTER_SECONDS)->isPast();
    }

    public function filesystem(): Filesystem
    {
        return $this->filesystem;
    }

    protected function withoutDefaultPort(Uri $uri): Uri
    {
        $scheme = Str::lower($uri->getScheme());
        $port = $uri->getPort();

        if ($port === null) {
            return $uri;
        }

        $defaultPorts = [
            'http' => 80,
            'https' => 443,
        ];

        if (Arr::get($defaultPorts, $scheme) === $port) {
            return $uri->withPort(null);
        }

        return $uri;
    }
}
