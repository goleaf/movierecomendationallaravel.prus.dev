<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

ini_set('memory_limit', '512M');

$basePath = dirname(__DIR__, 2);

require_once $basePath.'/vendor/autoload.php';

/** @var \Illuminate\Contracts\Foundation\Application $app */
$app = require $basePath.'/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$ssrmetricsConfigPath = $basePath.'/config/ssrmetrics.php';

if (is_file($ssrmetricsConfigPath)) {
    $app['config']->set('ssrmetrics', require $ssrmetricsConfigPath);
}

$helpersPath = $basePath.'/app/Support/helpers.php';

if (is_file($helpersPath)) {
    require_once $helpersPath;
}
