<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class RateLimitedClient
{
    protected string $baseUrl;

    protected float $timeout;

    protected int $retryAttempts;

    protected int $retryDelayMs;

    protected float $backoffMultiplier;

    protected int $backoffMaxDelayMs;

    protected int $rateLimitWindow;

    protected int $rateLimitAllowance;

    protected string $rateLimiterKey;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultQuery = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaultHeaders = [];

    public function __construct(
        protected HttpFactory $http,
        RateLimitedClientConfig $config,
    ) {
        $this->baseUrl = $config->baseUrl();
        $this->timeout = $config->timeout();
        $this->retryAttempts = $config->retryAttempts();
        $this->retryDelayMs = $config->retryDelayMs();
        $this->backoffMultiplier = $config->backoffMultiplier();
        $this->backoffMaxDelayMs = $config->backoffMaxDelayMs();
        $this->rateLimitWindow = $config->rateLimitWindow();
        $this->rateLimitAllowance = $config->rateLimitAllowance();
        $this->defaultQuery = $config->defaultQuery();
        $this->defaultHeaders = $config->defaultHeaders();
        $this->rateLimiterKey = $config->rateLimiterKey();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], array $options = []): array
    {
        return $this->request('GET', $path, $query, $options);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], array $options = []): array
    {
        $result = null;

        $executed = RateLimiter::attempt(
            $this->rateLimiterKey,
            $this->rateLimitAllowance,
            function () use (&$result, $method, $path, $query, $options): void {
                $result = $this->performRequest($method, $path, $query, $options);
            },
            $this->rateLimitWindow,
        );

        if ($executed === false) {
            throw new TooManyRequestsHttpException($this->rateLimitWindow, sprintf(
                'Rate limit exceeded for %s',
                $this->rateLimiterKey,
            ));
        }

        if ($result === null) {
            throw new RuntimeException('Request did not return a result.');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function performRequest(string $method, string $path, array $query = [], array $options = []): array
    {
        $maxAttempts = max(1, $this->retryAttempts + 1);
        $delay = $this->retryDelayMs;
        $lastException = null;
        $lastResponse = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $request = $this->buildRequest($options);

                $response = $request->send($method, ltrim($path, '/'), [
                    'query' => $this->buildQuery($query, $options),
                ]);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                if (! $this->shouldRetryResponse($response) || $attempt === $maxAttempts - 1) {
                    $response->throw();
                }

                $lastResponse = $response;
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt === $maxAttempts - 1) {
                    throw $lastException;
                }
            }

            if ($delay > 0) {
                usleep($delay * 1000);
            }

            $delay = $this->nextDelay($delay);
        }

        if ($lastResponse instanceof Response) {
            return $lastResponse->json() ?? [];
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('Unable to complete the HTTP request.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function buildRequest(array $options = []): PendingRequest
    {
        $request = $this->http->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->defaultQuery !== []) {
            $request = $request->withQueryParameters($this->defaultQuery);
        }

        if ($this->defaultHeaders !== []) {
            $request = $request->withHeaders($this->defaultHeaders);
        }

        if (($options['headers'] ?? []) !== []) {
            $request = $request->withHeaders((array) $options['headers']);
        }

        if (($options['options'] ?? []) !== []) {
            $request = $request->withOptions((array) $options['options']);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildQuery(array $query, array $options = []): array
    {
        $optionQuery = (array) ($options['query'] ?? []);
        $merged = array_merge($optionQuery, $query);

        return array_filter($merged, static fn ($value) => $value !== null);
    }

    protected function shouldRetryResponse(Response $response): bool
    {
        return $response->serverError() || $response->tooManyRequests();
    }

    protected function nextDelay(int $currentDelay): int
    {
        if ($currentDelay <= 0) {
            return $currentDelay;
        }

        $multiplier = $this->backoffMultiplier;

        if ($multiplier <= 1.0) {
            return $currentDelay;
        }

        $nextDelay = (int) ceil($currentDelay * $multiplier);

        if ($this->backoffMaxDelayMs > 0) {
            $nextDelay = min($nextDelay, $this->backoffMaxDelayMs);
        }

        return max($currentDelay, $nextDelay);
    }
}
