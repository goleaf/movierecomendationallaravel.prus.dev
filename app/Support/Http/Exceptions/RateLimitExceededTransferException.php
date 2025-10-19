<?php

declare(strict_types=1);

namespace App\Support\Http\Exceptions;

use GuzzleHttp\Exception\TransferException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimitExceededTransferException extends TransferException
{
    public function __construct(private readonly TooManyRequestsHttpException $httpException)
    {
        parent::__construct($httpException->getMessage(), $httpException->getCode(), $httpException);
    }

    public function toHttpException(): TooManyRequestsHttpException
    {
        return $this->httpException;
    }
}
