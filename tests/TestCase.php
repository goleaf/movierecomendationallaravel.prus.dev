<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use function base64_encode;
use function str_repeat;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
