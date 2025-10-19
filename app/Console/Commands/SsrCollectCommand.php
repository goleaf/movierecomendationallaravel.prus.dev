<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class SsrCollectCommand extends Command
{
    protected $signature = 'ssr:collect';

    protected $description = 'Dispatch internal SSR requests so metrics are captured.';

    public function handle(Kernel $kernel): int
    {
        if (! config('ssrmetrics.enabled')) {
            $this->warn('SSR metrics collection is disabled.');

            return static::SUCCESS;
        }

        $paths = collect(config('ssrmetrics.paths', []))
            ->filter(static fn ($path): bool => is_string($path) && trim($path) !== '')
            ->map(static fn (string $path): string => Str::start(trim($path), '/'))
            ->unique()
            ->values();

        if ($paths->isEmpty()) {
            $this->warn('No SSR metric paths configured.');

            return static::SUCCESS;
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $parsedUrl = parse_url($appUrl) ?: [];
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;
        $hostWithPort = $port !== null ? $host.':'.$port : $host;

        foreach ($paths as $path) {
            $response = null;
            $url = $scheme.'://'.$hostWithPort.$path;
            $request = Request::create($url, 'GET');
            $request->headers->set('host', $hostWithPort);

            if ($scheme === 'https') {
                $request->server->set('HTTPS', 'on');
            }

            try {
                $response = $kernel->handle($request);

                if ($response->isSuccessful() || $response->isRedirection()) {
                    $this->info(sprintf('Collected SSR metrics for %s (%d)', $path, $response->getStatusCode()));
                } else {
                    $this->warn(sprintf('Request to %s returned status %d', $path, $response->getStatusCode()));
                }
            } catch (Throwable $exception) {
                $this->error(sprintf('Failed to collect SSR metrics for %s: %s', $path, $exception->getMessage()));
                report($exception);

                continue;
            } finally {
                if ($response !== null) {
                    $kernel->terminate($request, $response);
                }
            }
        }

        $this->info('SSR metric collection completed.');

        return static::SUCCESS;
    }
}
