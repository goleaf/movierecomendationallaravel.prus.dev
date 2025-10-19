<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ProxyImageHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class CacheProxiedImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $url) {}

    public function handle(): void
    {
        try {
            $normalized = ProxyImageHelper::normalizeUrl($this->url);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Skipping proxy cache for invalid image URL.', [
                'url' => $this->url,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $disk = Storage::disk(ProxyImageHelper::diskName());
        $contentPath = ProxyImageHelper::contentPath($normalized);

        if ($disk->exists($contentPath)) {
            return;
        }

        try {
            $response = Http::accept('*/*')
                ->timeout(15)
                ->withHeaders([
                    'User-Agent' => config('app.user_agent', 'Laravel Proxied Image Cacher'),
                ])
                ->get($normalized);
        } catch (Throwable $exception) {
            Log::warning('Failed to download proxied image.', [
                'url' => $normalized,
                'exception' => $exception,
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('Remote image request returned a non-successful status.', [
                'url' => $normalized,
                'status' => $response->status(),
            ]);

            return;
        }

        $mimeType = $response->header('Content-Type') ?? '';

        if (! is_string($mimeType) || ! str_starts_with(strtolower($mimeType), 'image/')) {
            Log::warning('Skipping proxied image cache due to unsupported content type.', [
                'url' => $normalized,
                'content_type' => $mimeType,
            ]);

            return;
        }

        $body = $response->body();

        if ($body === '') {
            Log::warning('Skipping proxied image cache because the response body was empty.', [
                'url' => $normalized,
            ]);

            return;
        }

        $disk->put($contentPath, $body);

        $metadata = [
            'original_url' => $this->url,
            'normalized_url' => $normalized,
            'mime_type' => $mimeType,
            'content_length' => strlen($body),
            'stored_at' => now()->toIso8601String(),
        ];

        $disk->put(ProxyImageHelper::metadataPath($normalized), json_encode($metadata, JSON_THROW_ON_ERROR));
    }
}
