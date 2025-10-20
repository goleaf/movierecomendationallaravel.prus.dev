<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use Illuminate\Support\Uri;

class TmdbClient
{
    /**
     * @param  array<int, string>  $acceptedLocales
     */
    public function __construct(
        protected BatchedRateLimitedClient $client,
        protected string $defaultLocale,
        protected array $acceptedLocales = [],
    ) {
        if ($this->defaultLocale === '' && $this->acceptedLocales !== []) {
            $this->defaultLocale = $this->acceptedLocales[0];
        }

        if ($this->defaultLocale === '') {
            $this->defaultLocale = 'en-US';
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function findByImdbId(string $imdbId, ?string $language = null): array
    {
        $locale = $this->resolveLocale($language);

        $uri = $this->buildUri([
            'find',
            $imdbId,
        ], [
            'external_source' => 'imdb_id',
            'language' => $locale,
        ]);

        return $this->send($uri);
    }

    /**
     * @param  array<string, mixed>  $additionalQuery
     * @return array<string, mixed>
     */
    public function fetchTitle(int $id, string $language, string $mediaType = 'movie', array $additionalQuery = []): array
    {
        $mediaType = $this->normalizeMediaType($mediaType);
        $locale = $this->resolveLocale($language);

        $uri = $this->buildUri([
            $mediaType,
            $id,
        ], $additionalQuery + [
            'language' => $locale,
        ]);

        return $this->send($uri);
    }

    /**
     * @param  iterable<int|string, string>  $imdbIds
     * @return array<int|string, array<string, mixed>>
     */
    public function batchFindByImdbIds(iterable $imdbIds, ?string $language = null): array
    {
        $locale = $this->resolveLocale($language);

        $requests = [];

        foreach ($imdbIds as $key => $imdbId) {
            $uri = $this->buildUri([
                'find',
                $imdbId,
            ], [
                'external_source' => 'imdb_id',
                'language' => $locale,
            ]);

            $requests[$key] = [
                'path' => $uri->path() === '' ? '/' : $uri->path(),
                'query' => $uri->query()->all(),
            ];
        }

        return $this->client->batch($requests);
    }

    /**
     * @param  iterable<int|string, int>  $ids
     * @return array<int|string, array<string, mixed>>
     */
    public function batchFetchTitles(iterable $ids, string $language, string $mediaType = 'movie', array $additionalQuery = []): array
    {
        $mediaType = $this->normalizeMediaType($mediaType);
        $locale = $this->resolveLocale($language);

        $requests = [];

        foreach ($ids as $key => $id) {
            $uri = $this->buildUri([
                $mediaType,
                $id,
            ], $additionalQuery + [
                'language' => $locale,
            ]);

            $requests[$key] = [
                'path' => $uri->path() === '' ? '/' : $uri->path(),
                'query' => $uri->query()->all(),
            ];
        }

        return $this->client->batch($requests);
    }

    protected function resolveLocale(?string $locale): string
    {
        if ($locale !== null && $locale !== '' && in_array($locale, $this->acceptedLocales, true)) {
            return $locale;
        }

        return $this->defaultLocale;
    }

    protected function normalizeMediaType(string $mediaType): string
    {
        return match ($mediaType) {
            'tv' => 'tv',
            default => 'movie',
        };
    }

    protected function buildUri(array $segments, array $query = []): Uri
    {
        $uri = Uri::of()->withPath($this->buildPath($segments));

        if ($query !== []) {
            $uri = $uri->withQuery($query);
        }

        return $uri;
    }

    protected function buildPath(array $segments): string
    {
        if ($segments === []) {
            return '/';
        }

        $encodedSegments = array_map(
            static fn (string|int $segment): string => rawurlencode((string) $segment),
            $segments,
        );

        return implode('/', $encodedSegments);
    }

    protected function send(Uri $uri): array
    {
        return $this->client->get($uri->path(), $uri->query()->all());
    }
}
