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
        $locale = $this->resolveLocale($language);

        return $this->client->get("find/{$imdbId}", [
            'external_source' => 'imdb_id',
            'language' => $locale,
        ]);
    }

    /**
     * @param  array<string, mixed>  $additionalQuery
     * @return array<string, mixed>
     */
    public function fetchTitle(int $id, string $language, string $mediaType = 'movie', array $additionalQuery = []): array
    {
        $mediaType = $this->normalizeMediaType($mediaType);
        $locale = $this->resolveLocale($language);

        $query = $additionalQuery + [
            'language' => $locale,
        ];

        return $this->client->get("{$mediaType}/{$id}", $query);
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
