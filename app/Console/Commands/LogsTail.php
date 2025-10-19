<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class LogsTail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'logs:tail {--channel= : The log channel to read} {--lines=20 : The number of lines to display} {--follow : Stream the log output}';

    /**
     * The console command description.
     */
    protected $description = 'Tail a Laravel log channel with optional follow mode.';

    public function handle(): int
    {
        $channel = (string) ($this->option('channel') ?: config('logging.default'));
        if ($channel === '') {
            $this->components->error('Log channel must be provided.');

            return self::FAILURE;
        }

        $channelConfig = config("logging.channels.$channel");
        if (! is_array($channelConfig)) {
            $this->components->error("Log channel '{$channel}' is not defined.");

            return self::FAILURE;
        }

        $path = $this->resolveLogPath($channelConfig);
        if ($path === null) {
            $this->components->error("Log channel '{$channel}' does not use a file-based driver.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        if (! File::exists($path)) {
            File::put($path, '');
        }

        $lineCount = (int) $this->option('lines');
        if ($lineCount < 1) {
            $lineCount = 20;
        }

        $follow = (bool) $this->option('follow');

        $this->displayTail($path, $lineCount);

        if (! $follow) {
            return self::SUCCESS;
        }

        $this->stream($path);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $channelConfig
     */
    protected function resolveLogPath(array $channelConfig): ?string
    {
        $driver = Arr::get($channelConfig, 'driver');
        $path = Arr::get($channelConfig, 'path');

        if (! is_string($driver)) {
            return null;
        }

        if ($driver === 'single' && is_string($path)) {
            return $path;
        }

        if ($driver === 'daily' && is_string($path)) {
            $base = preg_replace('/\.log$/', '', $path);

            return sprintf('%s-%s.log', $base, now()->format('Y-m-d'));
        }

        return null;
    }

    protected function displayTail(string $path, int $lines): void
    {
        if (! File::exists($path)) {
            return;
        }

        $contents = file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($contents)) {
            return;
        }

        $selectedLines = array_slice($contents, -$lines);

        foreach ($selectedLines as $line) {
            $this->line(rtrim($line, "\r\n"));
        }
    }

    protected function stream(string $path): void
    {
        $this->components->info('Following log output. Press Ctrl+C to exit.');

        $resource = fopen($path, 'r');
        if ($resource === false) {
            $this->components->error('Unable to open log file for streaming.');

            return;
        }

        fseek($resource, 0, SEEK_END);

        while (true) {
            $line = fgets($resource);
            if ($line === false) {
                clearstatcache(false, $path);
                usleep(200000);

                continue;
            }

            $this->line(rtrim($line, "\r\n"));
        }
    }
}
