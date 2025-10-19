<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

class TmdbClient
{
    /**
     * @param  array<int, string>  $acceptedLocales
     */
    public function __construct(
        protected RateLimitedClient $client,
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
        $results = $this->batchFindByImdbIds([
            [
                'imdb_id' => $imdbId,
                'language' => $language,
                'key' => 'single',
            ],
        ]);

        return $results['single'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $additionalQuery
     * @return array<string, mixed>
     */
    public function fetchTitle(int $id, string $language, string $mediaType = 'movie', array $additionalQuery = []): array
    {
        $results = $this->batchFetchTitles([
            [
                'id' => $id,
                'language' => $language,
                'media_type' => $mediaType,
                'query' => $additionalQuery,
                'key' => 'single',
            ],
        ]);

        return $results['single'] ?? [];
    }

    /**
     * @param  list<string|array{imdb_id:string,key?:string,language?:string}>  $lookups
     * @return array<string, array<string, mixed>>
     */
    public function batchFindByImdbIds(array $lookups, ?string $defaultLanguage = null): array
    {
        if ($lookups === []) {
            return [];
        }

        $requests = [];
        $keys = [];

        foreach ($lookups as $lookup) {
            if (is_string($lookup)) {
                $imdbId = $lookup;
                $language = $defaultLanguage;
                $key = $imdbId;
            } else {
                $imdbId = (string) $lookup['imdb_id'];
                $language = $lookup['language'] ?? $defaultLanguage;
                $key = (string) ($lookup['key'] ?? $imdbId);
            }

            $locale = $this->resolveLocale($language);

            $requests[] = [
                'key' => $key,
                'path' => "find/{$imdbId}",
                'query' => [
                    'external_source' => 'imdb_id',
                    'language' => $locale,
                ],
            ];

            $keys[] = $key;
        }

        $responses = $this->client->batch($requests);

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $responses[$key] ?? [];
        }

        return $results;
    }

    /**
     * @param  list<array{id:int,key?:string,language?:string,media_type?:string,query?:array<string, mixed>}>  $titles
     * @return array<string, array<string, mixed>>
     */
    public function batchFetchTitles(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $requests = [];
        $keys = [];

        foreach ($titles as $definition) {
            $id = (int) $definition['id'];
            $mediaType = $this->normalizeMediaType((string) ($definition['media_type'] ?? 'movie'));
            $language = $definition['language'] ?? null;
            $locale = $this->resolveLocale($language);
            $key = (string) ($definition['key'] ?? sprintf('%s:%d:%s', $mediaType, $id, $locale));
            $query = (array) ($definition['query'] ?? []);

            $requests[] = [
                'key' => $key,
                'path' => "{$mediaType}/{$id}",
                'query' => $query + [
                    'language' => $locale,
                ],
            ];

            $keys[] = $key;
        }

        $responses = $this->client->batch($requests);

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $responses[$key] ?? [];
        }

        return $results;
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
}
