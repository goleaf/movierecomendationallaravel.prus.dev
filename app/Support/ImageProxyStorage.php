<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ImageProxyStorage
{
    public function __construct(
        private readonly FilesystemAdapter $filesystem,
        private readonly string $rootPath,
        private readonly int $ttl,
    ) {}

    public function imagePath(string $url): string
    {
        return $this->basePath($url).'/image';
    }

    public function metadataPath(string $url): string
    {
        return $this->basePath($url).'/metadata.json';
    }

    public function exists(string $url): bool
    {
        return $this->filesystem->exists($this->imagePath($url));
    }

    public function isFresh(string $url): bool
    {
        if (! $this->exists($url)) {
            return false;
        }

        if ($this->ttl <= 0) {
            return true;
        }

        $metadata = $this->metadata($url);

        if ($metadata === null) {
            return false;
        }

        $cachedAt = Arr::get($metadata, 'cached_at');

        if (! is_string($cachedAt)) {
            return false;
        }

        try {
            $timestamp = CarbonImmutable::parse($cachedAt);
        } catch (Throwable) {
            return false;
        }

        return $timestamp->addSeconds($this->ttl)->isFuture();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function write(string $url, string $contents, array $metadata): void
    {
        $this->filesystem->put($this->imagePath($url), $contents);

        $payload = array_merge([
            'source_url' => $url,
            'cached_at' => now()->toIso8601String(),
            'hash' => ProxyImageHelper::hashFor($url),
        ], $metadata);

        $this->filesystem->put(
            $this->metadataPath($url),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(string $url): ?array
    {
        $path = $this->metadataPath($url);

        if (! $this->filesystem->exists($path)) {
            return null;
        }

        try {
            $raw = $this->filesystem->get($path);
        } catch (FileNotFoundException) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function response(string $url, array $headers = []): StreamedResponse
    {
        $metadata = $this->metadata($url) ?? [];

        $defaultHeaders = array_filter([
            'Content-Type' => $metadata['content_type'] ?? null,
            'Content-Length' => isset($metadata['content_length']) ? (string) $metadata['content_length'] : null,
        ], static fn ($value): bool => $value !== null);

        return $this->filesystem->response(
            $this->imagePath($url),
            null,
            array_merge($defaultHeaders, $headers)
        );
    }

    private function basePath(string $url): string
    {
        $hash = ProxyImageHelper::hashFor($url);
        $segments = [
            $this->rootPath,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
        ];

        return trim(implode('/', $segments), '/');
    }
}
