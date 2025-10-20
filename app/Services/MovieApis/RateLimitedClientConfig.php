<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use InvalidArgumentException;

class RateLimitedClientConfig
{
    protected string $baseUrl;

    protected float $timeout;

    protected int $retryAttempts;

    protected int $retryDelayMs;

    protected float $backoffMultiplier;

    protected int $backoffMaxDelayMs;

    protected int $rateLimitWindow;

    protected int $rateLimitAllowance;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultQuery;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultHeaders;

    protected string $rateLimiterKey;

    protected int $batchConcurrency;

    protected int $batchRetryAttempts;

    protected int $batchRetryDelayMs;

    /**
     * @var array<string, mixed>
     */
    protected array $batchHeaders;

    /**
     * @param  array<string, mixed>  $retry
     * @param  array<string, mixed>  $backoff
     * @param  array<string, mixed>  $rateLimit
     * @param  array<string, mixed>  $defaultQuery
     * @param  array<string, mixed>  $defaultHeaders
     */
    public function __construct(
        string $baseUrl,
        float $timeout,
        array $retry = [],
        array $backoff = [],
        array $rateLimit = [],
        array $defaultQuery = [],
        array $defaultHeaders = [],
        ?string $rateLimiterKey = null,
        array $batch = [],
    ) {
        $this->baseUrl = $this->normaliseBaseUrl($baseUrl);
        $this->timeout = $this->normaliseTimeout($timeout);
        $this->retryAttempts = $this->normaliseNonNegativeInt($retry, 'attempts', 0);
        $this->retryDelayMs = $this->normaliseNonNegativeInt($retry, 'delay_ms', 0);
        $this->backoffMultiplier = $this->normalisePositiveFloat($backoff, 'multiplier', 1.0);
        $this->backoffMaxDelayMs = $this->normaliseNonNegativeInt($backoff, 'max_delay_ms', 0);
        $this->rateLimitWindow = $this->normalisePositiveInt($rateLimit, 'window', 60);
        $this->rateLimitAllowance = $this->normalisePositiveInt($rateLimit, 'allowance', 60);
        $this->defaultQuery = $this->normaliseKeyValueArray($defaultQuery);
        $this->defaultHeaders = $this->normaliseKeyValueArray($defaultHeaders);
        $this->rateLimiterKey = $this->normaliseRateLimiterKey($rateLimiterKey);
        $this->batchConcurrency = $this->normalisePositiveInt($batch, 'concurrency', 10);
        $this->batchRetryAttempts = $this->normaliseNonNegativeInt($batch['retry'] ?? [], 'attempts', 0);
        $this->batchRetryDelayMs = $this->normaliseNonNegativeInt($batch['retry'] ?? [], 'delay_ms', 0);
        $this->batchHeaders = $this->normaliseKeyValueArray($batch['headers'] ?? []);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function retryAttempts(): int
    {
        return $this->retryAttempts;
    }

    public function retryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    public function backoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    public function backoffMaxDelayMs(): int
    {
        return $this->backoffMaxDelayMs;
    }

    public function rateLimitWindow(): int
    {
        return $this->rateLimitWindow;
    }

    public function rateLimitAllowance(): int
    {
        return $this->rateLimitAllowance;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultQuery(): array
    {
        return $this->defaultQuery;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    public function rateLimiterKey(): string
    {
        return $this->rateLimiterKey;
    }

    public function batchConcurrency(): int
    {
        return $this->batchConcurrency;
    }

    public function batchRetryAttempts(): int
    {
        return $this->batchRetryAttempts;
    }

    public function batchRetryDelayMs(): int
    {
        return $this->batchRetryDelayMs;
    }

    /**
     * @return array<string, mixed>
     */
    public function batchHeaders(): array
    {
        return $this->batchHeaders;
    }

    protected function normaliseBaseUrl(string $baseUrl): string
    {
        $trimmed = rtrim(trim($baseUrl), '/');

        if ($trimmed === '') {
            throw new InvalidArgumentException('The base URL must be a non-empty string.');
        }

        return $trimmed.'/';
    }

    protected function normaliseTimeout(float $timeout): float
    {
        if ($timeout <= 0.0) {
            throw new InvalidArgumentException('Timeout must be greater than zero.');
        }

        return $timeout;
    }

    protected function normaliseNonNegativeInt(array $source, string $key, int $default): int
    {
        if (! array_key_exists($key, $source)) {
            return $default;
        }

        $value = $source[$key];

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('The %s value must be numeric.', $key));
        }

        $intValue = (int) $value;

        if ($intValue < 0) {
            throw new InvalidArgumentException(sprintf('The %s value must be zero or greater.', $key));
        }

        return $intValue;
    }

    protected function normalisePositiveFloat(array $source, string $key, float $default): float
    {
        if (! array_key_exists($key, $source)) {
            return $default;
        }

        $value = $source[$key];

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('The %s value must be numeric.', $key));
        }

        $floatValue = (float) $value;

        if ($floatValue <= 0.0) {
            throw new InvalidArgumentException(sprintf('The %s value must be greater than zero.', $key));
        }

        return $floatValue;
    }

    protected function normalisePositiveInt(array $source, string $key, int $default): int
    {
        if (! array_key_exists($key, $source)) {
            return $default;
        }

        $value = $source[$key];

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('The %s value must be numeric.', $key));
        }

        $intValue = (int) $value;

        if ($intValue <= 0) {
            throw new InvalidArgumentException(sprintf('The %s value must be greater than zero.', $key));
        }

        return $intValue;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normaliseKeyValueArray(array $values): array
    {
        $normalised = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('Configuration arrays must use non-empty string keys.');
            }

            if ($value === null) {
                continue;
            }

            $normalised[$key] = $value;
        }

        return $normalised;
    }

    protected function normaliseRateLimiterKey(?string $rateLimiterKey): string
    {
        if ($rateLimiterKey === null) {
            return sprintf('movie-apis:%s', md5($this->baseUrl));
        }

        $trimmed = trim($rateLimiterKey);

        if ($trimmed === '') {
            throw new InvalidArgumentException('The rate limiter key must be a non-empty string.');
        }

        return $trimmed;
    }
}
