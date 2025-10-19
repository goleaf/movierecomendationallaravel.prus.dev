<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ImageProxyStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CacheProxyImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public function __construct(public string $url, public string $cacheKey) {}

    public function handle(ImageProxyStorage $storage): void
    {
        if (! $this->isAllowedUrl($this->url)) {
            return;
        }

        $normalized = $storage->normalizeUrl($this->url);

        $response = Http::timeout(10)
            ->accept('image/*')
            ->withHeaders([
                'User-Agent' => config('app.name').' Image Proxy',
            ])
            ->get($this->url);

        if (! $response->successful()) {
            return;
        }

        $contentType = (string) $response->header('Content-Type');

        if (! Str::startsWith(Str::lower($contentType), 'image/')) {
            return;
        }

        $body = (string) $response->body();

        if ($body === '' || strlen($body) > ImageProxyStorage::MAX_BYTES) {
            return;
        }

        $extension = $storage->extensionForMime($contentType);
        $path = $storage->pathForCacheKey($this->cacheKey, $extension);
        $filesystem = $storage->filesystem();

        $existingMetadata = $storage->metadata($this->cacheKey);
        $previousExtension = Arr::get($existingMetadata, 'extension');
        if ($previousExtension && $previousExtension !== $extension) {
            $filesystem->delete($storage->pathForCacheKey($this->cacheKey, $previousExtension));
        }

        $filesystem->put($path, $body);

        $storage->putMetadata($this->cacheKey, array_filter([
            'normalized_url' => $normalized,
            'source_url' => $this->url,
            'content_type' => $contentType,
            'extension' => $extension,
            'content_length' => strlen($body),
            'last_modified' => $response->header('Last-Modified'),
            'fetched_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null));
    }

    protected function isAllowedUrl(string $url): bool
    {
        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
