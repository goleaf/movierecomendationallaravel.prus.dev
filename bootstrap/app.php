<?php

declare(strict_types=1);

use App\Console\Commands\AggregateCtrDailySnapshotsCommand;
use App\Console\Commands\DoctorCommand;
use App\Console\Commands\LogsTail;
use App\Console\Commands\QueueHealthcheckCommand;
use App\Console\Commands\SsrCollectCommand;
use App\Exceptions\Handler as ExceptionHandler;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AttachRequestContext;
use App\Http\Middleware\EnsureDeviceCookie;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SsrMetricsMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AggregateCtrDailySnapshotsCommand::class,
        DoctorCommand::class,
        LogsTail::class,
        QueueHealthcheckCommand::class,
        SsrCollectCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(AggregateCtrDailySnapshotsCommand::class)->dailyAt('01:00');
        $schedule->command(SsrCollectCommand::class)->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureDeviceCookie::class);
        $middleware->prepend(AttachRequestContext::class);
        $middleware->prepend(RequestId::class);

        $middleware->alias([
            'noindex' => NoIndex::class,
        ]);

        $middleware->append(AddCspHeaders::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->appendToGroup('web', HandleInertiaRequests::class);
        $middleware->appendToGroup('web', SsrMetricsMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ExceptionHandler::register($exceptions);
    })->create();
