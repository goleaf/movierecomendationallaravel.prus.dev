<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use App\Support\Http\MovieApiUriBuilder;
use App\Support\Http\Policy;
use Illuminate\Support\Uri;

class TmdbClient
{
    /**
     * @param  array<int, string>  $acceptedLocales
     */
    public function __construct(
        protected RateLimitedClient $client,
        protected string $defaultLocale,
        protected array $acceptedLocales = [],
        ?MovieApiUriBuilder $uriBuilder = null,
    ) {
        if ($this->defaultLocale === '' && $this->acceptedLocales !== []) {
            $this->defaultLocale = $this->acceptedLocales[0];
        }

        if ($this->defaultLocale === '') {
            $this->defaultLocale = 'en-US';
        }

        $this->uriBuilder = $uriBuilder ?? new MovieApiUriBuilder;
    }

    protected MovieApiUriBuilder $uriBuilder;

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
        return $this->uriBuilder->build($segments, $query);
    }

    protected function send(Uri $uri): array
    {
        return $this->client->get(
            $uri->path(),
            $uri->query()->all(),
            Policy::options('tmdb'),
        );
    }
}
