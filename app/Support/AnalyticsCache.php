<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

class AnalyticsCache
{
    private const CTR_TAG = 'analytics-ctr';

    private const TRENDS_TAG = 'analytics-trends';

    private const TTL_SECONDS = 300;

    /**
     * @template TValue
     *
     * @param  Closure():TValue  $callback
     * @return TValue
     */
    public static function rememberCtr(string $key, Closure $callback)
    {
        return cache()->tags([self::CTR_TAG])->remember($key, self::TTL_SECONDS, $callback);
    }

    /**
     * @template TValue
     *
     * @param  Closure():TValue  $callback
     * @return TValue
     */
    public static function rememberTrends(string $key, Closure $callback)
    {
        return cache()->tags([self::TRENDS_TAG])->remember($key, self::TTL_SECONDS, $callback);
    }

    public static function flushCtr(): void
    {
        cache()->tags([self::CTR_TAG])->flush();
    }

    public static function flushTrends(): void
    {
        cache()->tags([self::TRENDS_TAG])->flush();
    }
}
