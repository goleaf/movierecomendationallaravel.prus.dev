<?php

declare(strict_types=1);

namespace App\Services\MovieApis\Exceptions;

use Throwable;

class MovieApiRateLimitException extends MovieApiException
{
    public static function forKey(string $rateLimiterKey, int $window, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Rate limit exceeded for %s. Please retry in %d seconds.', $rateLimiterKey, $window),
            0,
            $previous,
        );
    }
}
