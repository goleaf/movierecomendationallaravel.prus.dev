<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class MetricsCache
{
    private const TAG = 'metrics:prometheus';

    private const TTL_SECONDS = 60;

    private const SEGMENTS_KEY = 'metrics:prometheus:segments';

    public function remember(string $segment, Closure $resolver): mixed
    {
        $store = $this->store();
        $key = $this->buildKey($segment);

        $this->rememberSegment($store, $segment);

        return $store->remember(
            $key,
            now()->addSeconds(self::TTL_SECONDS),
            $resolver
        );
    }

    public function flush(): void
    {
        $store = $this->store();
        $segments = $store->get(self::SEGMENTS_KEY, []);

        foreach ($segments as $segment) {
            $store->forget($this->buildKey($segment));
        }

        $store->forget(self::SEGMENTS_KEY);
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

    private function rememberSegment(Repository $store, string $segment): void
    {
        $segments = $store->get(self::SEGMENTS_KEY, []);

        if (in_array($segment, $segments, true)) {
            return;
        }

        $segments[] = $segment;

        $store->forever(self::SEGMENTS_KEY, $segments);
    }

    private function buildKey(string $segment): string
    {
        return self::TAG.':'.$segment;
    }
}
