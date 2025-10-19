<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use function base64_encode;
use function str_repeat;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
