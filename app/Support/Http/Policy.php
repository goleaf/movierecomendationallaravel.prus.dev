<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

final class Policy
{
    private const DEFAULT_CLIENT = 'default';

    /**
     * Create an HTTP client configured for external services.
     */
    public static function external(?string $client = null): PendingRequest
    {
        return self::for($client ?? self::DEFAULT_CLIENT);
    }

    /**
     * Create an HTTP client configured for the given client name.
     */
    public static function for(string $client): PendingRequest
    {
        return self::apply(Http::withHeaders([]), $client);
    }

    /**
     * Apply the default policy to the given request.
     */
    public static function apply(PendingRequest $request, string $client = self::DEFAULT_CLIENT): PendingRequest
    {
        $policy = self::resolvePolicy($client);

        $request->withHeaders($policy['headers']);

        if ($policy['timeout'] !== null) {
            $request->timeout($policy['timeout']);
        }

        if ($policy['connect_timeout'] !== null) {
            $request->connectTimeout($policy['connect_timeout']);
        }

        if ($policy['retry']['times'] > 0) {
            $request->retry(
                $policy['retry']['times'],
                $policy['retry']['sleep'],
                throw: ! $policy['retry']['idempotent'],
            );
        }

        return $request;
    }

    /**
     * Build rate limited client options for the given policy.
     *
     * @return array{headers: array<string, mixed>, options: array<string, mixed>}
     */
    public static function options(string $client = self::DEFAULT_CLIENT): array
    {
        $policy = self::resolvePolicy($client);

        $options = [];

        if ($policy['timeout'] !== null) {
            $options['timeout'] = $policy['timeout'];
        }

        if ($policy['connect_timeout'] !== null) {
            $options['connect_timeout'] = $policy['connect_timeout'];
        }

        return [
            'headers' => $policy['headers'],
            'options' => $options,
        ];
    }

    /**
     * @return array{headers: array<string, string>, timeout: float|null, connect_timeout: float|null, retry: array{times: int, sleep: int, idempotent: bool}}
     */
    private static function resolvePolicy(string $client): array
    {
        $configuration = self::mergeConfigurationLayers($client);

        return [
            'headers' => self::resolveHeaders($configuration),
            'timeout' => self::resolveFloat($configuration, 'timeout'),
            'connect_timeout' => self::resolveFloat($configuration, 'connect_timeout'),
            'retry' => [
                'times' => self::resolveInt($configuration, 'retry.times', 0),
                'sleep' => self::resolveInt($configuration, 'retry.sleep', 0),
                'idempotent' => self::resolveBool($configuration, 'retry.idempotent', true),
            ],
        ];
    }

    /**
     * @return array{headers: array<string, string>, timeout?: float, connect_timeout?: float, retry?: array<string, mixed>}
     */
    private static function mergeConfigurationLayers(string $client): array
    {
        $defaults = (array) Config::get('services.http.defaults', []);
        $clients = (array) Config::get('services.http.clients', []);
        $defaultClient = (array) ($clients[self::DEFAULT_CLIENT] ?? []);
        $namedClient = (array) ($clients[$client] ?? []);

        return array_replace_recursive($defaults, $defaultClient, $namedClient);
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, string>
     */
    private static function resolveHeaders(array $configuration): array
    {
        $headers = (array) ($configuration['headers'] ?? []);

        $headers = array_replace([
            'Accept' => 'application/json',
        ], $headers);

        if (! self::hasUserAgentHeader($headers)) {
            $headers['User-Agent'] = self::defaultUserAgent();
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function hasUserAgentHeader(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (is_string($name) && strtolower($name) === 'user-agent') {
                return true;
            }
        }

        return false;
    }

    private static function defaultUserAgent(): string
    {
        $applicationName = Config::get('app.name');

        $userAgent = is_string($applicationName) && $applicationName !== ''
            ? $applicationName
            : 'Laravel';

        return $userAgent.'/HttpClient';
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private static function resolveFloat(array $configuration, string $key): ?float
    {
        $value = data_get($configuration, $key);

        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private static function resolveInt(array $configuration, string $key, int $default): int
    {
        $value = data_get($configuration, $key);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private static function resolveBool(array $configuration, string $key, bool $default): bool
    {
        $value = data_get($configuration, $key);

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }
}
