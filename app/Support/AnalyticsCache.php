<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use DateTimeInterface;

class AnalyticsCache
{
    private const TTL_SECONDS = 300;

    private const TAG_CTR = 'analytics:ctr';

    private const TAG_TRENDS = 'analytics:trends';

    private const TAG_TRENDING = 'analytics:trending';

    public function __construct(private readonly MetricsCache $cache) {}

    public function rememberCtr(string $segment, array $parameters, Closure $resolver): mixed
    {
        return $this->remember(self::TAG_CTR, $segment, $parameters, $resolver);
    }

    public function rememberTrends(string $segment, array $parameters, Closure $resolver): mixed
    {
        return $this->remember(self::TAG_TRENDS, $segment, $parameters, $resolver);
    }

    public function rememberTrending(string $segment, array $parameters, Closure $resolver): mixed
    {
        return $this->remember(self::TAG_TRENDING, $segment, $parameters, $resolver);
    }

    public function flushCtr(): void
    {
        $this->cache->flush(self::TAG_CTR);
    }

    public function flushTrends(): void
    {
        $this->cache->flush(self::TAG_TRENDS);
    }

    public function flushTrending(): void
    {
        $this->cache->flush(self::TAG_TRENDING);
    }

    private function remember(string $tag, string $segment, array $parameters, Closure $resolver): mixed
    {
        $key = $this->buildKey($segment, $parameters);

        return $this->cache->remember($tag, $key, self::TTL_SECONDS, $resolver);
    }

    private function buildKey(string $segment, array $parameters): string
    {
        $normalized = $this->normalizeParameters($parameters);

        return 'analytics:'.$segment.':'.md5((string) json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function normalizeParameters(array $parameters): array
    {
        return array_map(function ($value) {
            if ($value instanceof DateTimeInterface) {
                return $value->format(DateTimeInterface::ATOM);
            }

            if (is_array($value)) {
                return $this->normalizeParameters($value);
            }

            if (is_object($value)) {
                return $this->normalizeParameters((array) $value);
            }

            return $value;
        }, $parameters);
    }
}
