<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class Policy
{
    private const DEFAULT_TIMEOUT_SECONDS = 15;

    private const DEFAULT_CONNECT_TIMEOUT_SECONDS = 5;

    private const RETRY_TIMES = 3;

    private const RETRY_DELAY_MILLISECONDS = 200;

    /**
     * Create an HTTP client configured for external services.
     */
    public static function external(): PendingRequest
    {
        return self::apply(Http::withHeaders([]));
    }

    /**
     * Apply the default policy to the given request.
     */
    public static function apply(PendingRequest $request): PendingRequest
    {
        $applicationName = config('app.name');
        $userAgent = is_string($applicationName) && $applicationName !== ''
            ? $applicationName
            : 'Laravel';

        $request->withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => $userAgent.'/HttpClient',
        ]);

        $request->timeout(self::DEFAULT_TIMEOUT_SECONDS);
        $request->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT_SECONDS);
        $request->retry(self::RETRY_TIMES, self::RETRY_DELAY_MILLISECONDS, throw: false);

        return $request;
    }
}
