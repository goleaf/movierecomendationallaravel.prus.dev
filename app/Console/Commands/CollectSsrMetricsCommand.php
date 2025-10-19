<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectSsrMetricsCommand extends Command
{
    protected $signature = 'ssr:collect {--path=* : Override the configured paths for this run}';

    protected $description = 'Trigger SSR metrics collection for configured paths';

    public function handle(HttpKernel $kernel): int
    {
        if (! config('ssrmetrics.enabled')) {
            $this->warn('SSR metrics collection is disabled.');

            return self::SUCCESS;
        }

        $configuredPaths = $this->option('path');
        $paths = collect($configuredPaths === [] ? config('ssrmetrics.paths', []) : $configuredPaths)
            ->map(fn ($path) => '/'.ltrim((string) $path, '/'))
            ->filter()
            ->unique()
            ->values();

        if ($paths->isEmpty()) {
            $this->warn('No SSR paths configured.');

            return self::SUCCESS;
        }

        $appUrl = config('app.url') ?: 'http://localhost';
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $isHttps = Str::startsWith($appUrl, 'https://');
        $port = parse_url($appUrl, PHP_URL_PORT) ?: ($isHttps ? 443 : 80);

        foreach ($paths as $path) {
            $request = Request::create($path, 'GET', server: [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
                'SERVER_PORT' => $port,
                'HTTPS' => $isHttps ? 'on' : 'off',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            $request->headers->set('User-Agent', 'SSR Metrics Cron');

            try {
                $response = $kernel->handle($request);
                $kernel->terminate($request, $response);

                if ($response->isSuccessful()) {
                    $this->info(sprintf('Collected SSR metrics for %s', $path));
                } else {
                    $this->warn(sprintf('Received HTTP %d for %s', $response->getStatusCode(), $path));
                }
            } catch (\Throwable $e) {
                $this->error(sprintf('Failed collecting SSR metrics for %s: %s', $path, $e->getMessage()));
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
