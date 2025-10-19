<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AttachRequestContext;
use App\Http\Middleware\EnsureDeviceCookie;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SsrMetricsMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureDeviceCookie::class);
        $middleware->prepend(AttachRequestContext::class);

        $middleware->append(AddCspHeaders::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->appendToGroup('web', HandleInertiaRequests::class);
        $middleware->appendToGroup('web', SsrMetricsMiddleware::class);

        $middleware->alias([
            'noindex' => \App\Http\Middleware\NoIndex::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
