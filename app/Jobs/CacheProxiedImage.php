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
use InvalidArgumentException;
use RuntimeException;

class CacheProxiedImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $url, public string $kind = 'poster')
    {
        $this->queue = 'images';
    }

    public function backoff(): array
    {
        return [60, 300, 600];
    }

    public function handle(ImageProxyStorage $storage): void
    {
        $normalizedKind = $this->normalizeKind($this->kind);
        $url = $this->validateUrl($this->url);

        $response = Http::accept('image/*')
            ->timeout(15)
            ->withHeaders([
                'User-Agent' => config('services.tmdb.key') ? 'MovieRecommendationProxy/1.0' : 'LaravelImageProxy/1.0',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Failed fetching image.');
        }

        $mimeType = $response->header('Content-Type');
        if (! is_string($mimeType) || ! Str::startsWith($mimeType, 'image/')) {
            throw new RuntimeException('Fetched resource is not an image.');
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('Empty image response.');
        }

        $storage->write($url, $normalizedKind, $body, $mimeType);
    }

    private function normalizeKind(string $kind): string
    {
        $normalized = strtolower($kind);

        if (! in_array($normalized, ['poster', 'backdrop'], true)) {
            throw new InvalidArgumentException('Unsupported artwork kind.');
        }

        return $normalized;
    }

    private function validateUrl(string $url): string
    {
        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        if (! is_string($filtered)) {
            throw new InvalidArgumentException('Invalid URL provided.');
        }

        $components = parse_url($filtered);
        $scheme = strtolower($components['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP/S URLs are allowed.');
        }

        $host = $components['host'] ?? '';
        if ($host === '') {
            throw new InvalidArgumentException('URL host is missing.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Private IP addresses are not allowed.');
            }
        }

        return $filtered;
    }
}
