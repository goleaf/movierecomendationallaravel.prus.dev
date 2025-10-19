<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use Illuminate\Support\Uri;

class OmdbClient
{
    /**
     * @param  array<string, mixed>  $defaultParameters
     */
    public function __construct(
        protected RateLimitedClient $client,
        protected array $defaultParameters = [],
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function findByImdbId(string $imdbId, array $parameters = []): array
    {
        $query = $this->buildQuery(['i' => $imdbId], $parameters);

        return $this->client->get($this->rootPath(), $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function findByTitle(string $title, array $parameters = []): array
    {
        $query = $this->buildQuery(['t' => $title], $parameters);

        return $this->client->get($this->rootPath(), $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function search(string $search, array $parameters = []): array
    {
        $query = $this->buildQuery(['s' => $search], $parameters);

        return $this->client->get($this->rootPath(), $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function buildQuery(array $query, array $overrides = []): array
    {
        $uri = Uri::of('/')
            ->withQuery($this->defaultParameters)
            ->withQuery($overrides)
            ->withQuery($query);

        return array_filter(
            $uri->query()->all(),
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    protected function rootPath(): Uri
    {
        return Uri::of('/');
    }
}
