<?php

declare(strict_types=1);

namespace App\Services\MovieApis\Exceptions;

use Throwable;

class MovieApiTransportException extends MovieApiException
{
    public static function requestFailed(string $method, string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Movie API request %s %s failed.', strtoupper($method), $path),
            0,
            $previous,
        );
    }

    public static function missingResult(string $method, string $path): self
    {
        return new self(sprintf('Movie API request %s %s did not return a result.', strtoupper($method), $path));
    }
}
