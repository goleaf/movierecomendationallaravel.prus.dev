<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        require_once __DIR__.'/../app/Support/helpers.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
