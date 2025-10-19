<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SessionCacheTest extends TestCase
{
    public function test_cache_based_session_handler_hits_store_for_each_read_when_memoization_disabled(): void
    {
        $handler = $this->prepareSessionHandler(memoize: false);

        $handler->write('session-id', 'serialized-data');

        $handler->read('session-id');
        $handler->read('session-id');

        $this->assertSame(2, TrackingCacheStore::$reads);
    }

    public function test_cache_based_session_handler_memoizes_store_reads_when_enabled(): void
    {
        $handler = $this->prepareSessionHandler(memoize: true);

        $handler->write('session-id', 'serialized-data');

        $handler->read('session-id');
        $handler->read('session-id');

        $this->assertSame(1, TrackingCacheStore::$reads);
    }

    private function prepareSessionHandler(bool $memoize): CacheBasedSessionHandler
    {
        TrackingCacheStore::resetReads();

        $store = new TrackingCacheStore;

        Cache::extend('session-tracking', function ($app, array $config) use ($store) {
            return $app['cache']->repository($store, $config);
        });

        config()->set('cache.stores.session-tracking', [
            'driver' => 'session-tracking',
        ]);

        config()->set('session.driver', 'memcached');
        config()->set('session.store', 'session-tracking');
        config()->set('session.cache.memoize', $memoize);

        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance(SessionManager::class);
        Session::clearResolvedInstance('session');
        Session::clearResolvedInstance('session.store');

        Cache::forgetDriver('session-tracking');

        $session = $this->app->make('session')->driver();

        return $session->getHandler();
    }
}

class TrackingCacheStore extends ArrayStore
{
    public static int $reads = 0;

    public static function resetReads(): void
    {
        static::$reads = 0;
    }

    public function get($key)
    {
        static::$reads++;

        return parent::get($key);
    }
}
