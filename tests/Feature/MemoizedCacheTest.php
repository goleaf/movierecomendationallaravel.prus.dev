<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class MemoizedCacheTest extends TestCase
{
    public function test_hot_path_caches_only_hit_underlying_store_once_per_request(): void
    {
        $filtersStore = new CountingArrayStore();
        $genresStore = new CountingArrayStore();

        Cache::extend('counting-filters', static fn ($app, array $config = []): Repository => new Repository($filtersStore));
        Cache::extend('counting-genres', static fn ($app, array $config = []): Repository => new Repository($genresStore));

        config()->set('cache.stores.counting-filters', ['driver' => 'counting-filters']);
        config()->set('cache.stores.counting-genres', ['driver' => 'counting-genres']);
        config()->set('cache.stores.hot_path_filters.store', 'counting-filters');
        config()->set('cache.stores.hot_path_genres.store', 'counting-genres');

        $filtersResolverCalls = 0;
        $genresResolverCalls = 0;

        Route::get('/memoized-cache-test', function () use (&$filtersResolverCalls, &$genresResolverCalls, $filtersStore, $genresStore): array {
            $filtersCache = Cache::store('hot_path_filters');
            $genresCache = Cache::store('hot_path_genres');

            $filtersCache->remember('filters.config', now()->addMinute(), function () use (&$filtersResolverCalls): array {
                $filtersResolverCalls++;

                return ['filters' => true];
            });

            $filtersCache->remember('filters.config', now()->addMinute(), function () use (&$filtersResolverCalls): array {
                $filtersResolverCalls++;

                return ['filters' => true];
            });

            $genresCache->remember('genres.list', now()->addMinute(), function () use (&$genresResolverCalls): array {
                $genresResolverCalls++;

                return ['sci-fi', 'drama'];
            });

            $genresCache->remember('genres.list', now()->addMinute(), function () use (&$genresResolverCalls): array {
                $genresResolverCalls++;

                return ['sci-fi', 'drama'];
            });

            return [
                'filters_resolver_calls' => $filtersResolverCalls,
                'genres_resolver_calls' => $genresResolverCalls,
                'filters_store_hits' => $filtersStore->getGetCount(),
                'genres_store_hits' => $genresStore->getGetCount(),
            ];
        });

        $response = $this->getJson('/memoized-cache-test');

        $response->assertOk();
        $response->assertJson([
            'filters_resolver_calls' => 1,
            'genres_resolver_calls' => 1,
            'filters_store_hits' => 1,
            'genres_store_hits' => 1,
        ]);
    }
}

final class CountingArrayStore implements Store
{
    private int $getCount = 0;

    private int $putCount = 0;

    public function __construct(private readonly ArrayStore $store = new ArrayStore()) {}

    public function get($key): mixed
    {
        $this->getCount++;

        return $this->store->get($key);
    }

    public function many(array $keys): array
    {
        $this->getCount += count($keys);

        return $this->store->many($keys);
    }

    public function put($key, $value, $seconds): bool
    {
        $this->putCount++;

        return $this->store->put($key, $value, $seconds);
    }

    public function putMany(array $values, $seconds): bool
    {
        $this->putCount += count($values);

        return $this->store->putMany($values, $seconds);
    }

    public function increment($key, $value = 1): int|bool
    {
        return $this->store->increment($key, $value);
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->store->decrement($key, $value);
    }

    public function forever($key, $value): bool
    {
        $this->putCount++;

        return $this->store->forever($key, $value);
    }

    public function forget($key): bool
    {
        return $this->store->forget($key);
    }

    public function flush(): bool
    {
        return $this->store->flush();
    }

    public function getPrefix(): string
    {
        return $this->store->getPrefix();
    }

    public function getGetCount(): int
    {
        return $this->getCount;
    }

    public function getPutCount(): int
    {
        return $this->putCount;
    }
}
