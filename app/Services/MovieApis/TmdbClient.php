<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

class TmdbClient extends RateLimitedApiClient
{
    public function __construct()
    {
        $config = config('services.tmdb', []);

        $options = [
            'timeout' => $config['timeout'] ?? 20,
            'rate_limit' => $config['rate_limit'] ?? [],
            'backoff' => $config['backoff'] ?? [],
            'headers' => array_merge(['Accept' => 'application/json'], $config['headers'] ?? []),
            'options' => $config['options'] ?? [],
            'query' => $config['query'] ?? [],
        ];

        parent::__construct($config['key'] ?? null, $config['base_url'] ?? 'https://api.themoviedb.org/3', $options);

        if ($this->enabled() && ($config['key'] ?? null)) {
            $this->defaultQuery['api_key'] = $config['key'];
        }
    }
}
