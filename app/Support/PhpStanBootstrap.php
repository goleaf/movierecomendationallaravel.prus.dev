<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Facade;

$basePath = dirname(__DIR__, 2);

require_once $basePath.'/vendor/autoload.php';

// Ensure Laravel helpers are available when PHPStan runs outside Artisan.
require_once $basePath.'/vendor/laravel/framework/src/Illuminate/Foundation/helpers.php';
require_once $basePath.'/vendor/laravel/framework/src/Illuminate/Support/helpers.php';
require_once $basePath.'/app/Support/helpers.php';

if (! function_exists('phpstan_env')) {
    function phpstan_env(string $key, string $value): void
    {
        if (! isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }

        if (! isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }

        putenv($key.'='.$value);
    }
}

phpstan_env('APP_ENV', 'phpstan');
phpstan_env('APP_DEBUG', 'false');
phpstan_env('APP_KEY', 'base64:'.base64_encode('phpstan-analysis-key-0123456789abcd'));
phpstan_env('CACHE_DRIVER', 'array');
phpstan_env('SESSION_DRIVER', 'array');
phpstan_env('QUEUE_CONNECTION', 'sync');
phpstan_env('FILESYSTEM_DISK', 'local');
phpstan_env('DB_CONNECTION', 'sqlite');
phpstan_env('DB_DATABASE', ':memory:');
phpstan_env('LOG_CHANNEL', 'stack');
phpstan_env('SSR_METRICS', 'true');

/** @var \Illuminate\Foundation\Application $app */
$app = require $basePath.'/bootstrap/app.php';

$app->useStoragePath($basePath.'/storage/phpstan');

Facade::setFacadeApplication($app);

foreach ([
    $app->storagePath(),
    $app->storagePath().'/framework',
    $app->storagePath().'/framework/cache',
    $app->storagePath().'/framework/cache/data',
    $app->storagePath().'/framework/sessions',
    $app->storagePath().'/framework/views',
    $app->storagePath().'/logs',
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config()->set('database.default', 'sqlite');
config()->set('database.connections.sqlite', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => false,
]);

config()->set('cache.default', 'array');
config()->set('cache.stores.array', [
    'driver' => 'array',
]);

config()->set('queue.default', 'sync');
config()->set('queue.connections.sync', [
    'driver' => 'sync',
]);

config()->set('filesystems.disks.local', array_merge(
    config('filesystems.disks.local', []),
    [
        'driver' => 'local',
        'root' => storage_path('app'),
        'throw' => false,
        'report' => false,
    ],
));

config()->set('filesystems.disks.metrics', [
    'driver' => 'local',
    'root' => storage_path('app/metrics'),
    'throw' => false,
    'report' => false,
]);

config()->set('logging.default', config('logging.default', 'stack'));

if (! function_exists('ssr_metrics_storage_path')) {
    function ssr_metrics_storage_path(): string
    {
        return storage_path('app/metrics/ssr.jsonl');
    }
}
