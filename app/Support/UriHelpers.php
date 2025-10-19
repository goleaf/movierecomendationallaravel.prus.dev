<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Uri;

class UriHelpers
{
    /**
     * @param  array<int, string|int>  $segments
     */
    public static function withPathSegments(Uri $uri, array $segments): Uri
    {
        if ($segments === []) {
            return $uri->withPath('/');
        }

        $normalized = array_filter(
            array_map(
                static fn (string|int $segment): string => trim((string) $segment, '/'),
                $segments,
            ),
            static fn (string $segment): bool => $segment !== '',
        );

        if ($normalized === []) {
            return $uri->withPath('/');
        }

        $encodedSegments = array_map(
            static fn (string $segment): string => rawurlencode($segment),
            $normalized,
        );

        return $uri->withPath(implode('/', $encodedSegments));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function withQuery(Uri $uri, array $query): Uri
    {
        $filtered = array_filter(
            $query,
            static fn ($value): bool => $value !== null && $value !== '',
        );

        if ($filtered === []) {
            return $uri;
        }

        return $uri->withQuery($filtered);
    }

    public static function signWithApiKey(Uri $uri, string $parameter, ?string $key): Uri
    {
        $key = is_string($key) ? trim($key) : null;

        if ($key === null || $key === '') {
            return $uri;
        }

        return $uri->withQuery([$parameter => $key]);
    }
}
