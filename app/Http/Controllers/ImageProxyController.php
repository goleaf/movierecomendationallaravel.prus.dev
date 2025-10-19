<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxiedImage;
use App\Support\ImageProxyStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request, ImageProxyStorage $storage): StreamedResponse
    {
        $encodedUrl = $request->query('url');
        $kind = $this->normalizeKind($request->query('kind', 'poster'));

        if (! is_string($encodedUrl) || $encodedUrl === '') {
            abort(404);
        }

        $decoded = base64_decode($encodedUrl, true);
        if ($decoded === false || $decoded === '') {
            abort(404);
        }

        $ttl = (int) config('services.artwork_proxy.ttl', 3600);

        $record = $storage->get($decoded, $kind);
        if ($record !== null && $this->isFresh($record['metadata']['last_fetched_at'], $ttl)) {
            return $this->streamResponse($storage, $record['path'], $record['metadata']['mime_type'], $record['metadata']['hash'], $ttl);
        }

        try {
            CacheProxiedImage::dispatchSync($decoded, $kind);
        } catch (InvalidArgumentException $e) {
            $this->abortAndReport($e);
        } catch (RuntimeException $e) {
            Log::warning('Failed caching proxied image.', [
                'exception' => $e,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $record = $storage->get($decoded, $kind);
        if ($record !== null && $this->isFresh($record['metadata']['last_fetched_at'], $ttl)) {
            return $this->streamResponse($storage, $record['path'], $record['metadata']['mime_type'], $record['metadata']['hash'], $ttl);
        }

        try {
            CacheProxiedImage::dispatch($decoded, $kind);
        } catch (\Throwable $e) {
            report($e);
        }

        abort(404);
    }

    private function normalizeKind(string $kind): string
    {
        $normalized = strtolower($kind);

        if (! in_array($normalized, ['poster', 'backdrop'], true)) {
            abort(404);
        }

        return $normalized;
    }

    private function isFresh(string $lastFetchedAt, int $ttl): bool
    {
        try {
            $fetchedAt = Carbon::parse($lastFetchedAt);
        } catch (\Throwable) {
            return false;
        }

        if ($ttl <= 0) {
            return true;
        }

        return $fetchedAt->greaterThanOrEqualTo(Carbon::now()->subSeconds($ttl));
    }

    private function streamResponse(ImageProxyStorage $storage, string $path, string $mimeType, string $hash, int $ttl): StreamedResponse
    {
        $stream = $storage->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        $response = response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => $ttl > 0 ? 'public, max-age='.$ttl : 'public',
            'ETag' => '"'.$hash.'"',
        ]);

        if ($ttl > 0) {
            $response->headers->set('Expires', Carbon::now()->addSeconds($ttl)->toRfc7231String());
        }

        return $response;
    }

    private function abortAndReport(\Throwable $throwable): never
    {
        Log::warning('Invalid image proxy request.', [
            'exception' => $throwable,
        ]);

        abort(404);
    }
}
