<?php

declare(strict_types=1);

namespace App\Services\MovieApis\Exceptions;

use Throwable;

class MovieApiRetryException extends MovieApiException
{
    public static function exhausted(string $method, string $path, int $attempts, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Movie API request %s %s failed after %d attempts.', strtoupper($method), $path, $attempts),
            0,
            $previous,
        );
    }
}
