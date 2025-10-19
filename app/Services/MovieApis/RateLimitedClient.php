<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use App\Services\MovieApis\Exceptions\MovieApiRequestException;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;

final class RateLimitedClient
{
    private readonly Closure $sleep;

    private readonly ?Closure $retryLogger;

    private readonly ?Closure $throttleLogger;

    /**
     * @param  callable(int $attempt, int $delayMilliseconds, Response|ConnectionException $reason): void|null  $retryLogger
     * @param  callable(string $limiterKey, int $waitSeconds): void|null  $throttleLogger
     */
    public function __construct(
        private readonly Factory $http,
        private readonly RateLimiter $rateLimiter,
        private readonly MovieApiClientConfig $config,
        ?callable $retryLogger = null,
        ?callable $throttleLogger = null,
        ?callable $sleep = null,
    ) {
        $this->retryLogger = $retryLogger !== null ? Closure::fromCallable($retryLogger) : null;
        $this->throttleLogger = $throttleLogger !== null ? Closure::fromCallable($throttleLogger) : null;

        $this->sleep = $sleep instanceof Closure ? $sleep : static function (int $milliseconds) use ($sleep): void {
            if ($sleep !== null) {
                $sleep($milliseconds);

                return;
            }

            usleep($milliseconds * 1000);
        };
    }

    /**
     * Perform an HTTP request while respecting provider rate limits and retry semantics.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $path, array $query = [], array $options = []): Response
    {
        $this->awaitCapacity();

        $lastException = null;
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $this->config->maxAttempts; $attempt++) {
            try {
                $response = $this->sendRequest($method, $path, $query, $options);

                if (! $this->shouldRetryResponse($response)) {
                    return $response;
                }

                $lastResponse = $response;
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }

            if ($attempt === $this->config->maxAttempts) {
                break;
            }

            $delayMilliseconds = $this->determineDelayMilliseconds($attempt, $lastResponse);

            $this->notifyRetry($attempt, $delayMilliseconds, $lastResponse, $lastException);

            ($this->sleep)($delayMilliseconds);

            $lastException = null;
            $lastResponse = null;
        }

        throw new MovieApiRequestException('Movie API request failed after exhausting retry attempts.', previous: $lastException);
    }

    private function awaitCapacity(): void
    {
        while (! $this->rateLimiter->attempt(
            $this->config->limiterKey,
            $this->config->requestsPerInterval,
            static fn () => null,
            $this->config->intervalSeconds,
        )) {
            $waitSeconds = max(1, $this->rateLimiter->availableIn($this->config->limiterKey));

            if ($this->throttleLogger !== null) {
                ($this->throttleLogger)($this->config->limiterKey, $waitSeconds);
            }

            ($this->sleep)($waitSeconds * 1000);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     */
    private function sendRequest(string $method, string $path, array $query, array $options): Response
    {
        $request = $this->createPendingRequest();

        $requestOptions = $options;
        $requestOptions['headers'] = array_merge($this->config->headers, $requestOptions['headers'] ?? []);
        $requestOptions['query'] = array_merge($requestOptions['query'] ?? [], $query);

        return $request->send($method, $path, $requestOptions);
    }

    private function createPendingRequest(): PendingRequest
    {
        return $this->http->baseUrl($this->config->baseUrl)
            ->timeout($this->config->timeoutSeconds);
    }

    private function shouldRetryResponse(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        if ($response->status() === 429) {
            return true;
        }

        return $response->serverError();
    }

    private function determineDelayMilliseconds(int $attempt, ?Response $response): int
    {
        if ($response !== null) {
            $retryAfter = $response->header('Retry-After');

            if ($retryAfter !== null) {
                if (is_numeric($retryAfter)) {
                    return (int) $retryAfter * 1000;
                }

                $retryTime = Carbon::parse($retryAfter);
                $milliseconds = $retryTime->diffInRealMilliseconds(Carbon::now(), false);

                if ($milliseconds > 0) {
                    return $milliseconds;
                }
            }
        }

        $delaySeconds = 2 ** ($attempt - 1);

        return (int) ($delaySeconds * 1000);
    }

    private function notifyRetry(int $attempt, int $delayMilliseconds, ?Response $response, ?ConnectionException $exception): void
    {
        if ($this->retryLogger === null) {
            return;
        }

        $reason = $response ?? $exception;

        if ($reason === null) {
            return;
        }

        ($this->retryLogger)($attempt, $delayMilliseconds, $reason);
    }
}
