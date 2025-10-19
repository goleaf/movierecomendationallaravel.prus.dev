<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
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
        $this->flushTag(self::TAG_CTR);
    }

    public function flushTrends(): void
    {
        $this->flushTag(self::TAG_TRENDS);
    }

    public function flushTrending(): void
    {
        $this->flushTag(self::TAG_TRENDING);
    }

    private function remember(string $tag, string $segment, array $parameters, Closure $resolver): mixed
    {
        $store = $this->store();
        $key = $this->buildKey($segment, $parameters);

        $this->rememberKey($store, $tag, $key);

        return $store->remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            $resolver
        );
    }

    private function store(): Repository
    {
        if (extension_loaded('redis')) {
            try {
                return Cache::store('redis');
            } catch (\Throwable) {
                // Fallback below.
            }
        }

        try {
            return Cache::store(config('cache.default'));
        } catch (\Throwable) {
            return Cache::store('array');
        }
    }

    private function flushTag(string $tag): void
    {
        $store = $this->store();
        $indexKey = $this->indexKey($tag);
        $keys = $store->get($indexKey, []);

        foreach ($keys as $key) {
            $store->forget($key);
        }

        $store->forget($indexKey);
    }

    private function rememberKey(Repository $store, string $tag, string $key): void
    {
        $indexKey = $this->indexKey($tag);
        $keys = $store->get($indexKey, []);

        if (in_array($key, $keys, true)) {
            return;
        }

        $keys[] = $key;

        $store->forever($indexKey, $keys);
    }

    private function indexKey(string $tag): string
    {
        return $tag.':keys';
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
