<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Csp\AddCspHeaders;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.redis.client', 'predis');
        config()->set('cache.default', 'array');
        config()->set('cache.stores.redis', [
            'driver' => 'array',
        ]);

        $this->withoutMiddleware(AddCspHeaders::class);
    }
}
