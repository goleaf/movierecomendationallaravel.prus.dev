<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

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
        $results = $this->batchFindByImdbIds([
            [
                'imdb_id' => $imdbId,
                'parameters' => $parameters,
                'key' => 'single',
            ],
        ]);

        return $results['single'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function findByTitle(string $title, array $parameters = []): array
    {
        $results = $this->batchFindByTitles([
            [
                'title' => $title,
                'parameters' => $parameters,
                'key' => 'single',
            ],
        ]);

        return $results['single'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function search(string $search, array $parameters = []): array
    {
        $query = $this->buildQuery(['s' => $search], $parameters);

        return $this->client->get('/', $query);
    }

    /**
     * @param  list<string|array{imdb_id:string,key?:string,parameters?:array<string,mixed>}>  $lookups
     * @return array<string, array<string, mixed>>
     */
    public function batchFindByImdbIds(array $lookups): array
    {
        if ($lookups === []) {
            return [];
        }

        $requests = [];
        $keys = [];

        foreach ($lookups as $lookup) {
            if (is_string($lookup)) {
                $imdbId = $lookup;
                $parameters = [];
                $key = $imdbId;
            } else {
                $imdbId = (string) $lookup['imdb_id'];
                $parameters = (array) ($lookup['parameters'] ?? []);
                $key = (string) ($lookup['key'] ?? $imdbId);
            }

            $requests[] = [
                'key' => $key,
                'path' => '/',
                'query' => $this->buildQuery(['i' => $imdbId], $parameters),
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
     * @param  list<string|array{title:string,key?:string,parameters?:array<string,mixed>}>  $titles
     * @return array<string, array<string, mixed>>
     */
    public function batchFindByTitles(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $requests = [];
        $keys = [];

        foreach ($titles as $definition) {
            if (is_string($definition)) {
                $title = $definition;
                $parameters = [];
                $key = $title;
            } else {
                $title = (string) $definition['title'];
                $parameters = (array) ($definition['parameters'] ?? []);
                $key = (string) ($definition['key'] ?? $title);
            }

            $requests[] = [
                'key' => $key,
                'path' => '/',
                'query' => $this->buildQuery(['t' => $title], $parameters),
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
}
