<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

class OmdbClient extends RateLimitedApiClient
{
    public function __construct()
    {
        $config = config('services.omdb', []);

        $options = [
            'timeout' => $config['timeout'] ?? 15,
            'rate_limit' => $config['rate_limit'] ?? [],
            'backoff' => $config['backoff'] ?? [],
            'headers' => array_merge(['Accept' => 'application/json'], $config['headers'] ?? []),
            'options' => $config['options'] ?? [],
            'query' => $config['query'] ?? [],
        ];

        parent::__construct($config['key'] ?? null, $config['base_url'] ?? 'https://www.omdbapi.com', $options);

        if ($this->enabled() && ($config['key'] ?? null)) {
            $this->defaultQuery['apikey'] = $config['key'];
        }
    }
}
