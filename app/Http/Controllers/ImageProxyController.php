<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RefreshProxiedImage;
use App\Support\ImageProxyStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ImageProxyController extends Controller
{
    private const URL_MAX_LENGTH = 2048;

    private const MAX_AGE_SECONDS = 3600;

    private const STALE_WHILE_REVALIDATE_SECONDS = 86400;

    public function __invoke(Request $request): SymfonyResponse
    {
        $encoded = (string) $request->query('u', '');

        if ($encoded === '') {
            abort(404);
        }

        $remoteUrl = $this->decodeUrl($encoded);

        if ($remoteUrl === null || ! $this->isAllowedUrl($remoteUrl)) {
            abort(404);
        }

        $cachePath = ImageProxyStorage::cachePath($remoteUrl);

        if (ImageProxyStorage::exists($cachePath)) {
            $meta = ImageProxyStorage::readMeta($cachePath);
            $fetchedAt = $this->resolveFetchedAt($meta, $cachePath);

            $response = $this->buildResponse($request, $cachePath, $meta, $fetchedAt);

            if (! $this->isFresh($fetchedAt)) {
                $this->queueRefresh($remoteUrl);
            }

            return $response;
        }

        $fresh = ImageProxyStorage::refresh($remoteUrl);

        if ($fresh === null) {
            abort(404);
        }

        $fetchedAt = $this->resolveFetchedAt($fresh['meta'], $fresh['cache_path']);

        return $this->buildResponse($request, $fresh['cache_path'], $fresh['meta'], $fetchedAt);
    }

    private function isAllowedUrl(string $url): bool
    {
        if (strlen($url) > self::URL_MAX_LENGTH) {
            return false;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        return true;
    }

    private function decodeUrl(string $encoded): ?string
    {
        $normalized = strtr($encoded, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    private function resolveFetchedAt(array $meta, string $cachePath): ?CarbonImmutable
    {
        $value = $meta['fetched_at'] ?? null;

        if (is_string($value)) {
            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                // fall back to file system metadata
            }
        }

        return ImageProxyStorage::lastModified($cachePath);
    }

    private function buildResponse(
        Request $request,
        string $cachePath,
        array $meta,
        ?CarbonImmutable $fetchedAt
    ): SymfonyResponse {
        $etag = $this->makeEtag($cachePath, $meta, $fetchedAt);
        $headers = $this->responseHeaders($cachePath, $meta, $fetchedAt, $etag);

        if ($etag !== null && $request->headers->get('if-none-match') === $etag) {
            return response('', 304, $headers);
        }

        if ($fetchedAt !== null) {
            $ifModifiedSince = $request->headers->get('if-modified-since');

            if ($ifModifiedSince !== null) {
                try {
                    $clientDate = CarbonImmutable::parse($ifModifiedSince);

                    if ($fetchedAt->lte($clientDate)) {
                        return response('', 304, $headers);
                    }
                } catch (Throwable) {
                    // ignore invalid header values
                }
            }
        }

        return ImageProxyStorage::streamedResponse($cachePath, $headers);
    }

    private function responseHeaders(
        string $cachePath,
        array $meta,
        ?CarbonImmutable $fetchedAt,
        ?string $etag
    ): array {
        $headers = [
            'Content-Type' => ImageProxyStorage::mimeType($cachePath, $meta),
            'Cache-Control' => sprintf(
                'public, max-age=%d, stale-while-revalidate=%d, stale-if-error=%d',
                self::MAX_AGE_SECONDS,
                self::STALE_WHILE_REVALIDATE_SECONDS,
                self::STALE_WHILE_REVALIDATE_SECONDS,
            ),
            'Content-Length' => (string) ImageProxyStorage::size($cachePath, $meta),
            'Accept-Ranges' => 'none',
        ];

        if ($etag !== null) {
            $headers['ETag'] = $etag;
        }

        if ($fetchedAt !== null) {
            $headers['Last-Modified'] = $fetchedAt->toRfc7231String();
            $age = max(0, min(
                $fetchedAt->diffInSeconds(now()),
                self::MAX_AGE_SECONDS + self::STALE_WHILE_REVALIDATE_SECONDS,
            ));
            $headers['Age'] = (string) $age;
        }

        return $headers;
    }

    private function makeEtag(string $cachePath, array $meta, ?CarbonImmutable $fetchedAt): ?string
    {
        $parts = [
            $cachePath,
            (string) ($meta['source'] ?? ''),
            (string) ($meta['content_length'] ?? ImageProxyStorage::size($cachePath, $meta)),
        ];

        if ($fetchedAt !== null) {
            $parts[] = $fetchedAt->toIso8601String();
        }

        $fingerprint = implode('|', $parts);

        if ($fingerprint === '') {
            return null;
        }

        return '"'.sha1($fingerprint).'"';
    }

    private function isFresh(?CarbonImmutable $fetchedAt): bool
    {
        if ($fetchedAt === null) {
            return false;
        }

        return $fetchedAt->addSeconds(self::MAX_AGE_SECONDS)->isFuture();
    }

    private function queueRefresh(string $remoteUrl): void
    {
        RefreshProxiedImage::dispatch($remoteUrl)->afterResponse();
    }
}
