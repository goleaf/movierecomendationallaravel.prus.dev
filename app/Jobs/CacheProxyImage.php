<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ImageProxyStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CacheProxyImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $url, public bool $force = false)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $normalized = ImageProxyStorage::normalizeUrl($this->url);

        if ($normalized === null) {
            return;
        }

        $key = ImageProxyStorage::cacheKey($normalized);
        $metadata = ImageProxyStorage::readMetadata($key);

        if (! $this->force && ! ImageProxyStorage::shouldRefresh($key, $metadata)) {
            return;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 500)
                ->accept('image/*')
                ->withHeaders([
                    'User-Agent' => config('app.url', config('app.name', 'MovieRec')).' image-proxy',
                ])
                ->get($normalized);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($response->failed()) {
            Log::warning('Image proxy fetch failed.', [
                'url' => $normalized,
                'status' => $response->status(),
            ]);

            return;
        }

        $body = $response->body();

        if (! is_string($body) || $body === '') {
            return;
        }

        $contentType = $response->header('Content-Type');

        if (! is_string($contentType) || ! str_starts_with(strtolower($contentType), 'image/')) {
            return;
        }

        $extension = ImageProxyStorage::extensionFromContentType($contentType)
            ?? ImageProxyStorage::extensionFromUrl($normalized)
            ?? 'bin';

        $path = ImageProxyStorage::imagePath($key, $extension);

        ImageProxyStorage::ensureDirectory($key);
        ImageProxyStorage::deleteExisting($key, $path);
        ImageProxyStorage::disk()->put($path, $body);

        $etag = $this->cleanHeader($response->header('ETag'));
        $lastModified = $this->cleanHeader($response->header('Last-Modified'));

        $metadata = [
            'url' => $normalized,
            'path' => $path,
            'extension' => $extension,
            'content_type' => strtolower($contentType),
            'content_length' => strlen($body),
            'etag' => $etag,
            'last_modified' => $lastModified,
            'cached_at' => now()->toIso8601String(),
            'checksum' => hash('sha256', $body),
        ];

        ImageProxyStorage::writeMetadata($key, $metadata);
    }

    private function cleanHeader(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
