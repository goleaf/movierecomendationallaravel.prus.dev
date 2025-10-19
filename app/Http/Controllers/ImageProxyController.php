<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxyImage;
use App\Support\ImageProxyStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request): Response|BinaryFileResponse
    {
        $encodedUrl = $request->query('url');
        $hash = $request->query('hash');
        $shouldRefresh = ImageProxyStorage::shouldRefresh($request->boolean('refresh'));

        if (! is_string($encodedUrl) || $encodedUrl === '' || ! is_string($hash) || $hash === '') {
            abort(404);
        }

        $normalized = base64_decode($encodedUrl, true);

        if (! is_string($normalized) || $normalized === '') {
            abort(404);
        }

        $normalized = ImageProxyStorage::normalizeUrl($normalized);

        if ($normalized === null || $hash !== ImageProxyStorage::hash($normalized)) {
            abort(404);
        }

        $path = ImageProxyStorage::relativePath($normalized);
        $disk = ImageProxyStorage::disk();

        if ($shouldRefresh) {
            ImageProxyStorage::forget($normalized);
        }

        if (! $disk->exists($path)) {
            CacheProxyImage::dispatchSync($normalized, $shouldRefresh);
        }

        if (! $disk->exists($path)) {
            abort(404);
        }

        $absolutePath = $disk->path($path);

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'max-age=604800, public',
        ]);
    }
}
