<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

class AnalyticsCache
{
    private const TTL_SECONDS = 300;

    private const TAG_CTR = 'analytics:ctr';

    private const TAG_TRENDS = 'analytics:trends';

    private const TAG_TRENDING = 'analytics:trending';

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
        $this->tags(self::TAG_CTR)->flush();
    }

    public function flushTrends(): void
    {
        $this->tags(self::TAG_TRENDS)->flush();
    }

    public function flushTrending(): void
    {
        $this->tags(self::TAG_TRENDING)->flush();
    }

    private function remember(string $tag, string $segment, array $parameters, Closure $resolver): mixed
    {
        $key = $this->buildKey($segment, $parameters);

        return $this->tags($tag)->remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            $resolver
        );
    }

    private function tags(string $tag): TaggedCache
    {
        return Cache::store('redis')->tags([$tag]);
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
