<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Support\Uri;

class MovieApiUriBuilder
{
    public function __construct(
        protected ?string $apiKeyParameter = null,
        protected ?string $apiKey = null,
    ) {}

    /**
     * @param  array<int, string|int|null>  $segments
     * @param  array<string, mixed>  $query
     */
    public function build(array $segments = [], array $query = []): Uri
    {
        $path = $this->joinPathSegments($segments);

        $uri = Uri::of('/')
            ->withPath($path);

        $parameters = $this->buildQuery($query);

        if ($parameters !== []) {
            $uri = $uri->withQuery($parameters);
        }

        return $uri;
    }

    /**
     * @param  array<int, string|int|null>  $segments
     */
    public function joinPathSegments(array $segments): string
    {
        $encoded = [];

        foreach ($segments as $segment) {
            if ($segment === null) {
                continue;
            }

            $stringSegment = (string) $segment;

            if ($stringSegment === '') {
                continue;
            }

            $encoded[] = rawurlencode($stringSegment);
        }

        if ($encoded === []) {
            return '/';
        }

        return implode('/', $encoded);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function buildQuery(array $query): array
    {
        return $this->signQuery($this->filterQuery($query));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function filterQuery(array $query): array
    {
        $filtered = [];

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function signQuery(array $query): array
    {
        if (
            $this->apiKeyParameter !== null
            && $this->apiKey !== null
            && $this->apiKey !== ''
            && ! array_key_exists($this->apiKeyParameter, $query)
        ) {
            $query = [$this->apiKeyParameter => $this->apiKey] + $query;
        }

        return $query;
    }
}
