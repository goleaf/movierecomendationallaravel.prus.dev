<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Cache\MemoPolicy;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class MemoPolicyTest extends TestCase
{
    public function test_filters_hot_path_uses_memoized_store(): void
    {
        $filtersStore = new CountingArrayStore;

        Cache::extend('counting-filters', fn ($app, array $config = []): Repository => new Repository($filtersStore));

        config()->set('cache.stores.counting-filters', ['driver' => 'counting-filters']);
        config()->set('cache.stores.hot_path_filters', ['driver' => 'memoized', 'store' => 'counting-filters']);

        Cache::forgetDriver(['counting-filters', 'hot_path_filters']);

        $policy = app(MemoPolicy::class);

        $computed = 0;

        $result = $policy->rememberFilters('demo', ['foo' => 'bar'], function () use (&$computed): array {
            $computed++;

            return ['value' => 'filters'];
        });

        $resultAgain = $policy->rememberFilters('demo', ['foo' => 'bar'], function () use (&$computed): array {
            $computed++;

            return ['value' => 'filters'];
        });

        self::assertSame($result, $resultAgain);
        self::assertSame(1, $computed);
    }

    public function test_genres_hot_path_uses_memoized_store(): void
    {
        $genresStore = new CountingArrayStore;

        Cache::extend('counting-genres', fn ($app, array $config = []): Repository => new Repository($genresStore));

        config()->set('cache.stores.counting-genres', ['driver' => 'counting-genres']);
        config()->set('cache.stores.hot_path_genres', ['driver' => 'memoized', 'store' => 'counting-genres']);

        Cache::forgetDriver(['counting-genres', 'hot_path_genres']);

        $policy = app(MemoPolicy::class);

        $computed = 0;

        $value = $policy->rememberGenres('demo', ['foo' => 'bar'], function () use (&$computed): array {
            $computed++;

            return ['value' => 'genres'];
        });

        $valueAgain = $policy->rememberGenres('demo', ['foo' => 'bar'], function () use (&$computed): array {
            $computed++;

            return ['value' => 'genres'];
        });

        self::assertSame($value, $valueAgain);
        self::assertSame(1, $computed);
    }
}

final class CountingArrayStore implements Store
{
    private int $getCount = 0;

    private int $putCount = 0;

    public function __construct(private readonly ArrayStore $store = new ArrayStore) {}

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
