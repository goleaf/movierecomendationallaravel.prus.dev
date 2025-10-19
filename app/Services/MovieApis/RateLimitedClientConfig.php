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

    protected int $retryJitterMs;

    protected int $concurrency;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultQuery;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultHeaders;

    protected string $rateLimiterKey;

    protected string $serviceName;

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
        ?int $concurrency = null,
        ?int $retryJitterMs = null,
        ?string $serviceName = null,
    ) {
        $this->baseUrl = $this->normaliseBaseUrl($baseUrl);
        $this->timeout = $this->normaliseTimeout($timeout);
        $this->retryAttempts = $this->normaliseNonNegativeInt($retry, 'attempts', 0);
        $this->retryDelayMs = $this->normaliseNonNegativeInt($retry, 'delay_ms', 0);
        $this->backoffMultiplier = $this->normalisePositiveFloat($backoff, 'multiplier', 1.0);
        $this->backoffMaxDelayMs = $this->normaliseNonNegativeInt($backoff, 'max_delay_ms', 0);
        $this->rateLimitWindow = $this->normalisePositiveInt($rateLimit, 'window', 60);
        $this->rateLimitAllowance = $this->normalisePositiveInt($rateLimit, 'allowance', 60);
        $this->retryJitterMs = $this->normaliseOptionalNonNegativeInt($retryJitterMs, 0);
        $this->concurrency = $this->normaliseOptionalPositiveInt($concurrency, 1);
        $this->defaultQuery = $this->normaliseKeyValueArray($defaultQuery);
        $this->defaultHeaders = $this->normaliseKeyValueArray($defaultHeaders);
        $this->rateLimiterKey = $this->normaliseRateLimiterKey($rateLimiterKey);
        $this->serviceName = $this->normaliseServiceName($serviceName);
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

    public function retryJitterMs(): int
    {
        return $this->retryJitterMs;
    }

    public function concurrency(): int
    {
        return $this->concurrency;
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

    public function serviceName(): string
    {
        return $this->serviceName;
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

    protected function normaliseOptionalNonNegativeInt(?int $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if ($value < 0) {
            throw new InvalidArgumentException('Configuration values must be zero or greater.');
        }

        return $value;
    }

    protected function normaliseOptionalPositiveInt(?int $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Configuration values must be greater than zero.');
        }

        return $value;
    }

    protected function normaliseServiceName(?string $serviceName): string
    {
        if ($serviceName === null) {
            return 'movie-api';
        }

        $trimmed = trim($serviceName);

        if ($trimmed === '') {
            throw new InvalidArgumentException('The service name must be a non-empty string.');
        }

        return $trimmed;
    }
}
