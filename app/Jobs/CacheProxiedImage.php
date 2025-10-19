<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ImageProxyStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CacheProxiedImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $url)
    {
        $queue = Config::get('image-proxy.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(ImageProxyStorage $storage): void
    {
        if ($storage->isFresh($this->url)) {
            return;
        }

        try {
            $response = Http::accept('image/*')->get($this->url);
        } catch (Throwable $exception) {
            Log::warning('Failed to download proxied image.', [
                'url' => $this->url,
                'exception' => $exception,
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('Received non-success status for proxied image.', [
                'url' => $this->url,
                'status' => $response->status(),
            ]);

            return;
        }

        $body = $response->body();

        $contentLengthHeader = $response->header('Content-Length');
        $contentLength = is_numeric($contentLengthHeader)
            ? (int) $contentLengthHeader
            : strlen($body);

        $storage->write($this->url, $body, [
            'content_type' => $response->header('Content-Type'),
            'content_length' => $contentLength,
            'last_modified' => $response->header('Last-Modified'),
            'etag' => $response->header('ETag'),
            'status' => $response->status(),
        ]);
    }
}
