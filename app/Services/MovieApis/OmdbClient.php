<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use App\Support\UriHelpers;
use Illuminate\Support\Uri;

class OmdbClient
{
    /**
     * @param  array<string, mixed>  $defaultParameters
     */
    public function __construct(
        protected RateLimitedClient $client,
        protected array $defaultParameters = [],
        protected ?string $apiKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function findByImdbId(string $imdbId, array $parameters = []): array
    {
        $uri = $this->buildUri(['i' => $imdbId], $parameters);

        return $this->send($uri);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function findByTitle(string $title, array $parameters = []): array
    {
        $uri = $this->buildUri(['t' => $title], $parameters);

        return $this->send($uri);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function search(string $search, array $parameters = []): array
    {
        $uri = $this->buildUri(['s' => $search], $parameters);

        return $this->send($uri);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function buildQuery(array $query, array $overrides = []): array
    {
        $merged = array_merge($this->defaultParameters, $overrides, $query);

        return array_filter(
            $merged,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $overrides
     */
    protected function buildUri(array $query, array $overrides = []): Uri
    {
        $uri = Uri::of('/');

        $parameters = $this->buildQuery($query, $overrides);

        if ($parameters !== []) {
            $uri = UriHelpers::withQuery($uri, $parameters);
        }

        return UriHelpers::signWithApiKey($uri, 'apikey', $this->apiKey);
    }

    protected function send(Uri $uri): array
    {
        return $this->client->get($uri->path(), $uri->query()->all());
    }
}
