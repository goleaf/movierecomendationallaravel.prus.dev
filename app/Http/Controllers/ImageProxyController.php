<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxyImage;
use App\Support\ImageProxyStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageProxyController
{
    public function __invoke(Request $request, string $cacheKey, ImageProxyStorage $storage): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $source = $request->query('source');

        if (blank($source)) {
            throw new NotFoundHttpException('Missing source parameter.');
        }

        $normalized = $storage->normalizeUrl($source);
        $expectedCacheKey = $storage->cacheKeyFor($normalized);

        if (! hash_equals($expectedCacheKey, $cacheKey)) {
            throw new NotFoundHttpException('Cache key mismatch.');
        }

        $disk = Storage::disk('public');
        $filesystem = $storage->filesystem();

        $metadata = $storage->metadata($cacheKey);
        $path = $storage->pathForCacheKey($cacheKey, Arr::get($metadata, 'extension'));

        if (! $filesystem->exists($path)) {
            CacheProxyImage::dispatchSync($normalized, $cacheKey);

            $metadata = $storage->metadata($cacheKey);
            $path = $storage->pathForCacheKey($cacheKey, Arr::get($metadata, 'extension'));
        }

        if (! $filesystem->exists($path)) {
            throw new NotFoundHttpException('Artwork not available.');
        }

        if ($storage->isStale($metadata)) {
            CacheProxyImage::dispatch($normalized, $cacheKey);
        }

        $headers = $this->headersFor($metadata);

        return $disk->response($path, null, $headers);
    }

    protected function headersFor(?array $metadata): array
    {
        $headers = [
            'Cache-Control' => 'public, max-age='.ImageProxyStorage::STALE_AFTER_SECONDS.', stale-while-revalidate='.ImageProxyStorage::STALE_AFTER_SECONDS,
        ];

        if ($metadata) {
            if ($contentType = Arr::get($metadata, 'content_type')) {
                $headers['Content-Type'] = $contentType;
            }

            if ($lastModified = Arr::get($metadata, 'last_modified')) {
                $headers['Last-Modified'] = $lastModified;
            }

            if ($length = Arr::get($metadata, 'content_length')) {
                $headers['Content-Length'] = (string) $length;
            }
        }

        return $headers;
    }
}
