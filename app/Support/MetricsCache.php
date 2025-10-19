<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\TaggableStore;
use Illuminate\Support\Str;
use Throwable;

class MetricsCache
{
    private const VERSION_PREFIX = 'metrics:tag-version:';

    public function __construct(private readonly CacheManager $cacheManager) {}

    public function remember(string $tag, string $key, int $ttlSeconds, Closure $resolver): mixed
    {
        $store = $this->store();

        if ($this->supportsTags($store)) {
            return $store->tags([$tag])->remember(
                $key,
                now()->addSeconds($ttlSeconds),
                $resolver,
            );
        }

        $namespacedKey = $this->namespacedKey($store, $tag, $key);

        return $store->remember(
            $namespacedKey,
            now()->addSeconds($ttlSeconds),
            $resolver,
        );
    }

    public function flush(string $tag): void
    {
        $store = $this->store();

        if ($this->supportsTags($store)) {
            $store->tags([$tag])->flush();

            return;
        }

        $this->rotateTagVersion($store, $tag);
    }

    private function store(): Repository
    {
        $preferred = $this->preferredStore();

        if ($preferred !== null) {
            return $this->cacheManager->store($preferred);
        }

        return $this->cacheManager->store();
    }

    private function preferredStore(): ?string
    {
        $stores = config('cache.stores', []);

        if (! is_array($stores) || ! array_key_exists('redis', $stores)) {
            return null;
        }

        try {
            $this->cacheManager->store('redis');

            return 'redis';
        } catch (Throwable) {
            return null;
        }
    }

    private function supportsTags(Repository $repository): bool
    {
        return $repository->getStore() instanceof TaggableStore;
    }

    private function namespacedKey(Repository $store, string $tag, string $key): string
    {
        $version = $this->resolveTagVersion($store, $tag);

        return 'metrics:'.$tag.':'.$version.':'.$key;
    }

    private function resolveTagVersion(Repository $store, string $tag): string
    {
        $versionKey = $this->versionKey($tag);
        $version = $store->get($versionKey);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = Str::uuid()->toString();
        $store->forever($versionKey, $version);

        return $version;
    }

    private function rotateTagVersion(Repository $store, string $tag): void
    {
        $store->forever($this->versionKey($tag), Str::uuid()->toString());
    }

    private function versionKey(string $tag): string
    {
        return self::VERSION_PREFIX.$tag;
    }
}
