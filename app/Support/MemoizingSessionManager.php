<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\SessionManager;

class MemoizingSessionManager extends SessionManager
{
    /**
     * Create the cache based session handler instance.
     */
    protected function createCacheHandler($driver)
    {
        $store = $this->config->get('session.store') ?: $driver;

        $cacheManager = $this->container->make('cache');

        $repository = clone ($this->config->get('session.cache.memoize', false)
            ? $cacheManager->memo($store)
            : $cacheManager->store($store));

        return new CacheBasedSessionHandler(
            $repository,
            $this->config->get('session.lifetime')
        );
    }
}
