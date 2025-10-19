<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxiedImage;
use App\Support\ProxyImageHelper;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageProxyController extends Controller
{
    public function show(Request $request): StreamedResponse|Response
    {
        $validated = $request->validate([
            'url' => ['required', 'string'],
        ]);

        try {
            $normalized = ProxyImageHelper::normalizeUrl($validated['url']);
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid image URL.');
        }

        $disk = Storage::disk(ProxyImageHelper::diskName());
        $contentPath = ProxyImageHelper::contentPath($normalized);
        $metadataPath = ProxyImageHelper::metadataPath($normalized);

        if (! $disk->exists($contentPath)) {
            CacheProxiedImage::dispatchSync($validated['url']);
        }

        if (! $disk->exists($contentPath)) {
            abort(404);
        }

        try {
            $stream = $disk->readStream($contentPath);
        } catch (FileNotFoundException) {
            abort(404);
        }

        if (! is_resource($stream)) {
            abort(404);
        }

        $metadata = [];

        if ($disk->exists($metadataPath)) {
            try {
                $metadata = json_decode($disk->get($metadataPath), true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (JsonException) {
                $metadata = [];
            }
        }

        $headers = [
            'Content-Type' => is_string($metadata['mime_type'] ?? null)
                ? $metadata['mime_type']
                : 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Proxy-Source' => (string) ($metadata['original_url'] ?? $validated['url']),
            'Content-Disposition' => 'inline; filename="'.basename($contentPath).'"',
        ];

        if (isset($metadata['content_length'])) {
            $headers['Content-Length'] = (string) $metadata['content_length'];
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
