<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

/**
 * Configuration options for the rate limited movie API client.
 *
 * @property-read string $baseUrl Base URL that is prefixed to every request path.
 * @property-read array<string, string> $headers Additional headers appended to each request.
 * @property-read float $timeoutSeconds Timeout applied to every request, in seconds.
 * @property-read int $maxAttempts Maximum number of attempts, including the first request, before failing.
 * @property-read string $limiterKey Rate limiter key used to coordinate throttling across processes.
 * @property-read int $requestsPerInterval Number of allowed requests per configured interval.
 * @property-read int $intervalSeconds Interval length, in seconds, used by the rate limiter window.
 */
final class MovieApiClientConfig
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly array $headers = [],
        public readonly float $timeoutSeconds = 10.0,
        public readonly int $maxAttempts = 3,
        public readonly string $limiterKey = 'movie-api',
        public readonly int $requestsPerInterval = 30,
        public readonly int $intervalSeconds = 60,
    ) {}
}
