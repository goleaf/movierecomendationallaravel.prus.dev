<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\TmdbClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

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

            $client = new RateLimitedClient(
                $app->make(HttpFactory::class),
                $this->normaliseBaseUrl((string) ($config['base_url'] ?? 'https://api.themoviedb.org/3/')),
                (float) ($config['timeout'] ?? 10),
                (array) ($config['retry'] ?? []),
                (array) ($config['backoff'] ?? []),
                (array) ($config['rate_limit'] ?? []),
                [
                    'api_key' => $config['key'] ?? null,
                ],
                [],
                'tmdb:'.md5((string) ($config['key'] ?? 'tmdb')),
                [],
                $this->resolveLogger($config['log_channel'] ?? null),
            );

            return new TmdbClient($client, $defaultLocale, $acceptedLocales);
        });

        $this->app->singleton(OmdbClient::class, function ($app): OmdbClient {
            $config = (array) config('services.omdb', []);

            $client = new RateLimitedClient(
                $app->make(HttpFactory::class),
                $this->normaliseBaseUrl((string) ($config['base_url'] ?? 'https://www.omdbapi.com/')),
                (float) ($config['timeout'] ?? 10),
                (array) ($config['retry'] ?? []),
                (array) ($config['backoff'] ?? []),
                (array) ($config['rate_limit'] ?? []),
                [
                    'apikey' => $config['key'] ?? null,
                ],
                [],
                'omdb:'.md5((string) ($config['key'] ?? 'omdb')),
                [],
                $this->resolveLogger($config['log_channel'] ?? null),
            );

            return new OmdbClient($client, (array) ($config['default_params'] ?? []));
        });
    }

    protected function normaliseBaseUrl(string $baseUrl): string
    {
        $trimmed = rtrim($baseUrl, '/');

        return $trimmed !== '' ? $trimmed.'/' : $baseUrl;
    }

    protected function resolveLogger(?string $channel): ?LoggerInterface
    {
        if ($channel === null || $channel === '') {
            return null;
        }

        try {
            return Log::channel($channel);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Failed to resolve movie API logger channel.', [
                'channel' => $channel,
                'exception' => $exception,
            ]);
        }

        return null;
    }
}
