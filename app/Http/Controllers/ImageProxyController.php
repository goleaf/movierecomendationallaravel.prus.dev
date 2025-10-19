<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxyImage;
use App\Support\ImageProxyStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request, string $key): Response|StreamedResponse
    {
        $encoded = $request->query('url');

        if (! is_string($encoded) || $encoded === '') {
            abort(404);
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            abort(404);
        }

        $normalized = ImageProxyStorage::normalizeUrl($decoded);

        if ($normalized === null) {
            abort(404);
        }

        if ($key !== ImageProxyStorage::cacheKey($normalized)) {
            abort(404);
        }

        $metadata = ImageProxyStorage::readMetadata($key);
        $path = ImageProxyStorage::storedPath($key, $metadata);

        if ($path === null) {
            CacheProxyImage::dispatchSync($normalized, true);

            $metadata = ImageProxyStorage::readMetadata($key);
            $path = ImageProxyStorage::storedPath($key, $metadata);

            if ($path === null) {
                return redirect()->away($normalized);
            }
        } else {
            if (ImageProxyStorage::shouldRefresh($key, $metadata)) {
                CacheProxyImage::dispatch($normalized);
            }
        }

        $etag = is_array($metadata) ? ($metadata['etag'] ?? null) : null;
        $lastModified = is_array($metadata) ? ($metadata['last_modified'] ?? null) : null;

        if (is_string($etag) && $etag !== '') {
            $normalizedEtag = trim($etag, "\"' ");
            $incoming = $request->header('If-None-Match');

            if (is_string($incoming) && trim($incoming, "\"' ") === $normalizedEtag) {
                $response = response()
                    ->noContent(304)
                    ->setEtag($normalizedEtag);

                $response->headers->set('Cache-Control', 'public, max-age=86400');

                if (is_string($lastModified) && $lastModified !== '') {
                    $response->headers->set('Last-Modified', $lastModified);
                }

                return $response;
            }
        }

        if (is_string($lastModified) && $lastModified !== '') {
            $incomingModifiedSince = $request->header('If-Modified-Since');

            if (is_string($incomingModifiedSince) && strtotime($incomingModifiedSince) !== false && strtotime($lastModified) !== false) {
                if (strtotime($incomingModifiedSince) >= strtotime($lastModified)) {
                    $response = response()->noContent(304);
                    $response->headers->set('Last-Modified', $lastModified);
                    $response->headers->set('Cache-Control', 'public, max-age=86400');

                    return $response;
                }
            }
        }

        $disk = ImageProxyStorage::disk();
        $stream = $disk->readStream($path);

        if ($stream === false) {
            abort(404);
        }

        $contentType = is_array($metadata) ? ($metadata['content_type'] ?? null) : null;
        $contentLength = is_array($metadata) ? ($metadata['content_length'] ?? null) : null;

        if (! is_int($contentLength) || $contentLength <= 0) {
            $size = $disk->size($path);

            if (is_int($size) && $size > 0) {
                $contentLength = $size;
            }
        }

        $response = response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => is_string($contentType) && $contentType !== '' ? $contentType : 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);

        if (is_int($contentLength) && $contentLength > 0) {
            $response->headers->set('Content-Length', (string) $contentLength);
        }

        if (is_string($lastModified) && $lastModified !== '') {
            $response->headers->set('Last-Modified', $lastModified);
        }

        if (is_string($etag) && $etag !== '') {
            $response->setEtag(trim($etag, "\"' "));
        }

        return $response;
    }
}
