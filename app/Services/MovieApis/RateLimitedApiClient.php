<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

abstract class RateLimitedApiClient
{
    protected ?string $apiKey;

    protected string $baseUrl;

    protected int $timeout;

    protected int $maxAttempts;

    protected int $decaySeconds;

    protected int $maxRetries;

    protected int $initialBackoffMs;

    protected float $backoffMultiplier;

    protected int $maxBackoffMs;

    /**
     * @var array<string, string>
     */
    protected array $defaultHeaders = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaultOptions = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaultQuery = [];

    /**
     * @var array<int, int>
     */
    protected array $retryStatusCodes = [429, 500, 502, 503, 504, 522, 524];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(?string $apiKey, string $baseUrl, array $config = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = (int) ($config['timeout'] ?? 20);
        $this->maxAttempts = max(1, (int) ($config['rate_limit']['max_attempts'] ?? 30));
        $this->decaySeconds = max(1, (int) ($config['rate_limit']['decay_seconds'] ?? 10));
        $this->maxRetries = max(0, (int) ($config['backoff']['max_retries'] ?? 3));
        $this->initialBackoffMs = max(1, (int) ($config['backoff']['initial_ms'] ?? 500));
        $this->backoffMultiplier = (float) ($config['backoff']['multiplier'] ?? 2.0);
        $this->maxBackoffMs = max(0, (int) ($config['backoff']['max_ms'] ?? 10_000));
        $this->defaultHeaders = $config['headers'] ?? [];
        $this->defaultOptions = $config['options'] ?? [];
        $this->defaultQuery = $config['query'] ?? [];
    }

    public function enabled(): bool
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     */
    public function get(string $path, array $query = [], array $options = []): Response
    {
        return $this->send('get', $path, $query, [], $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     */
    public function post(string $path, array $payload = [], array $query = [], array $options = []): Response
    {
        return $this->send('post', $path, $query, $payload, $options);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    protected function send(string $method, string $path, array $query, array $payload, array $options): Response
    {
        if (! $this->enabled()) {
            throw new RuntimeException(sprintf('%s is disabled because the API key is missing.', static::class));
        }

        $attempt = 0;
        $lastResponse = null;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            if ($attempt > 0) {
                $this->backoff($attempt);
            }

            $this->acquireSlot();

            try {
                $request = $this->buildRequest($query, $options);
                $response = $request->{$method}($this->formatPath($path), $payload);
                $lastResponse = $response;

                if (! $this->shouldRetry($response)) {
                    return $response;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            $attempt++;
        }

        if ($lastResponse !== null) {
            return $lastResponse;
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new RuntimeException(sprintf('Unable to reach %s after retries.', static::class));
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     */
    protected function buildRequest(array $query, array $options): PendingRequest
    {
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : $this->timeout;
        $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);
        $extraOptions = array_merge($this->defaultOptions, $options['options'] ?? []);
        $queryOptions = $options['query'] ?? [];

        $fullQuery = $this->buildQuery(array_merge($queryOptions, $query));
        $extraOptions['query'] = $fullQuery;

        $request = Http::baseUrl($this->baseUrl)->timeout($timeout);

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        if ($extraOptions !== []) {
            $request = $request->withOptions($extraOptions);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function buildQuery(array $query): array
    {
        $defaults = array_filter($this->defaultQuery, static fn ($value) => $value !== null);
        $merged = array_merge($defaults, $query);

        return array_filter($merged, static fn ($value) => $value !== null);
    }

    protected function formatPath(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return ltrim($path, '/');
    }

    protected function shouldRetry(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        return in_array($response->status(), $this->retryStatusCodes, true);
    }

    protected function backoff(int $attempt): void
    {
        $delay = (int) round($this->initialBackoffMs * ($this->backoffMultiplier ** ($attempt - 1)));

        if ($this->maxBackoffMs > 0) {
            $delay = min($delay, $this->maxBackoffMs);
        }

        usleep(max(1, $delay) * 1000);
    }

    protected function acquireSlot(): void
    {
        $key = $this->rateLimiterKey();

        while (! RateLimiter::attempt($key, $this->maxAttempts, static fn (): bool => true, $this->decaySeconds)) {
            $wait = RateLimiter::availableIn($key);

            if ($wait <= 0) {
                usleep(50_000);

                continue;
            }

            usleep($wait * 1_000_000);
        }
    }

    protected function rateLimiterKey(): string
    {
        $seed = $this->apiKey ?? $this->baseUrl;

        return sprintf('movie-api:%s:%s', static::class, sha1($seed));
    }
}
