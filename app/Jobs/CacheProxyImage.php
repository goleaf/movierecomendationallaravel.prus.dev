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
use Illuminate\Support\Str;

class CacheProxyImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private string $originalUrl;

    private bool $forceRefresh;

    public function __construct(string $url, bool $forceRefresh = false)
    {
        $this->originalUrl = $url;
        $this->forceRefresh = $forceRefresh;
    }

    public function handle(): void
    {
        $normalized = ImageProxyStorage::normalizeUrl($this->originalUrl);

        if ($normalized === null) {
            return;
        }

        $path = ImageProxyStorage::relativePath($normalized);
        $disk = ImageProxyStorage::disk();

        if (! $this->forceRefresh && $disk->exists($path)) {
            return;
        }

        if ($this->forceRefresh) {
            ImageProxyStorage::forget($normalized);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($normalized);
        } catch (\Throwable $exception) {
            report($exception);

            return;
        }

        if (! $response->successful()) {
            return;
        }

        $contentType = $response->header('Content-Type');

        if (is_array($contentType)) {
            $contentType = Str::of(reset($contentType) ?: '')->lower()->toString();
        } elseif (is_string($contentType)) {
            $contentType = Str::of($contentType)->lower()->toString();
        } else {
            $contentType = '';
        }

        if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
            return;
        }

        $disk->put($path, $response->body());
    }
}
