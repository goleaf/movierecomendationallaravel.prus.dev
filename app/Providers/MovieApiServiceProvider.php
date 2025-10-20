<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MovieApis\BatchedRateLimitedClient;
use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use App\Services\MovieApis\TmdbClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class MovieApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TmdbClient::class, function ($app): TmdbClient {
            $config = (array) config('services.tmdb', []);
            $acceptedLocales = array_values(array_filter(
                (array) ($config['accepted_locales'] ?? []),
                static fn ($value) => is_string($value) && $value !== ''
            ));
            $defaultLocale = (string) ($config['default_locale'] ?? ($acceptedLocales[0] ?? 'en-US'));

            $clientConfig = new RateLimitedClientConfig(
                baseUrl: (string) ($config['base_url'] ?? 'https://api.themoviedb.org/3/'),
                timeout: (float) ($config['timeout'] ?? 10),
                retry: (array) ($config['retry'] ?? []),
                backoff: (array) ($config['backoff'] ?? []),
                rateLimit: (array) ($config['rate_limit'] ?? []),
                defaultQuery: [
                    'api_key' => $config['key'] ?? null,
                ],
                defaultHeaders: (array) ($config['headers'] ?? []),
                rateLimiterKey: 'tmdb:'.md5((string) ($config['key'] ?? 'tmdb')),
            );

            $client = new BatchedRateLimitedClient(
                $app->make(HttpFactory::class),
                $clientConfig,
                'tmdb',
            );

            return new TmdbClient($client, $defaultLocale, $acceptedLocales);
        });

        $this->app->singleton(OmdbClient::class, function ($app): OmdbClient {
            $config = (array) config('services.omdb', []);

            $clientConfig = new RateLimitedClientConfig(
                baseUrl: (string) ($config['base_url'] ?? 'https://www.omdbapi.com/'),
                timeout: (float) ($config['timeout'] ?? 10),
                retry: (array) ($config['retry'] ?? []),
                backoff: (array) ($config['backoff'] ?? []),
                rateLimit: (array) ($config['rate_limit'] ?? []),
                defaultQuery: [
                    'apikey' => $config['key'] ?? null,
                ],
                defaultHeaders: (array) ($config['headers'] ?? []),
                rateLimiterKey: 'omdb:'.md5((string) ($config['key'] ?? 'omdb')),
            );

            $client = new BatchedRateLimitedClient(
                $app->make(HttpFactory::class),
                $clientConfig,
                'omdb',
            );

            return new OmdbClient($client, (array) ($config['default_params'] ?? []));
        });
    }
}
