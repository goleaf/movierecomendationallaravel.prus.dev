<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use App\Support\Http\MovieApiUriBuilder;
use App\Support\Http\Policy;
use Illuminate\Support\Uri;

class OmdbClient
{
    /**
     * @param  array<string, mixed>  $defaultParameters
     */
    public function __construct(
        protected RateLimitedClient $client,
        protected array $defaultParameters = [],
        ?MovieApiUriBuilder $uriBuilder = null,
    ) {
        $this->uriBuilder = $uriBuilder ?? new MovieApiUriBuilder;
    }

    protected MovieApiUriBuilder $uriBuilder;

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
        return array_merge($this->defaultParameters, $overrides, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $overrides
     */
    protected function buildUri(array $query, array $overrides = []): Uri
    {
        $parameters = $this->buildQuery($query, $overrides);

        return $this->uriBuilder->build([], $parameters);
    }

    protected function send(Uri $uri): array
    {
        return $this->client->get(
            $uri->path(),
            $uri->query()->all(),
            Policy::options('omdb'),
        );
    }
}
