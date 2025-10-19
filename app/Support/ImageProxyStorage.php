<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;

class ImageProxyStorage
{
    private const BASE_DIRECTORY = 'artwork';

    /**
     * @var string[]
     */
    private const ALLOWED_KINDS = ['poster', 'backdrop'];

    private FilesystemAdapter $disk;

    public function __construct(?FilesystemAdapter $disk = null)
    {
        $this->disk = $disk ?? Storage::disk('public');
    }

    public function getDisk(): FilesystemAdapter
    {
        return $this->disk;
    }

    public function imagePath(string $url, string $kind): string
    {
        $this->assertValidKind($kind);

        $hash = hash('sha256', $kind.'|'.$url);
        $segments = [
            self::BASE_DIRECTORY,
            $kind,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
        ];

        return implode('/', $segments).'/image';
    }

    public function metadataPath(string $url, string $kind): string
    {
        $this->assertValidKind($kind);

        $hash = hash('sha256', $kind.'|'.$url);
        $segments = [
            self::BASE_DIRECTORY,
            $kind,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
        ];

        return implode('/', $segments).'/meta.json';
    }

    /**
     * @return array{path: string, metadata: array{hash: string, last_fetched_at: string, mime_type: string}}
     */
    public function write(string $url, string $kind, string $contents, string $mimeType, ?CarbonInterface $fetchedAt = null): array
    {
        $path = $this->imagePath($url, $kind);
        $metadataPath = $this->metadataPath($url, $kind);

        $hash = hash('sha256', $contents);
        $metadata = [
            'hash' => $hash,
            'last_fetched_at' => ($fetchedAt ?? Carbon::now())->toIso8601String(),
            'mime_type' => $mimeType,
        ];

        $this->disk->put($path, $contents);
        $this->disk->put($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));

        return [
            'path' => $path,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{path: string, metadata: array{hash: string, last_fetched_at: string, mime_type: string}}|null
     */
    public function get(string $url, string $kind): ?array
    {
        $path = $this->imagePath($url, $kind);
        $metadataPath = $this->metadataPath($url, $kind);

        if (! $this->disk->exists($path) || ! $this->disk->exists($metadataPath)) {
            return null;
        }

        try {
            $metadataContents = $this->disk->get($metadataPath);
        } catch (\Throwable) {
            return null;
        }

        try {
            /** @var array{hash?: string, last_fetched_at?: string, mime_type?: string} $metadata */
            $metadata = json_decode($metadataContents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! isset($metadata['hash'], $metadata['last_fetched_at'], $metadata['mime_type'])) {
            return null;
        }

        return [
            'path' => $path,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return resource|null
     */
    public function readStream(string $path)
    {
        return $this->disk->readStream($path) ?: null;
    }

    private function assertValidKind(string $kind): void
    {
        if (! in_array($kind, self::ALLOWED_KINDS, true)) {
            throw new InvalidArgumentException('Unsupported artwork kind.');
        }
    }
}
