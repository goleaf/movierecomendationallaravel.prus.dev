<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;

class SsrMetricsFallbackStore
{
    public function __construct(private FilesystemFactory $filesystem) {}

    public function append(array $payload): void
    {
        $disk = $this->disk();
        $path = $this->incomingFile();

        $directory = dirname($path);
        if ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $disk->makeDirectory($directory);
        }

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        if (method_exists($disk, 'append')) {
            $disk->append($path, $encoded);

            return;
        }

        $existing = $disk->exists($path) ? (string) $disk->get($path) : '';
        $separator = $existing === '' ? '' : "\n";

        $disk->put($path, $existing.$separator.$encoded);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readIncoming(): array
    {
        $disk = $this->disk();
        $path = $this->incomingFile();

        if (! $disk->exists($path)) {
            return [];
        }

        $content = trim((string) $disk->get($path));

        if ($content === '') {
            return [];
        }

        $lines = preg_split("/\r?\n/", $content) ?: [];
        $records = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readRecovery(): array
    {
        $disk = $this->disk();
        $path = $this->recoveryFile();

        if ($path === '' || ! $disk->exists($path)) {
            return [];
        }

        $content = trim((string) $disk->get($path));

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function diskName(): string
    {
        return (string) config('ssrmetrics.storage.fallback.disk', 'local');
    }

    private function disk(): Filesystem
    {
        return $this->filesystem->disk($this->diskName());
    }

    private function incomingFile(): string
    {
        return (string) config('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');
    }

    private function recoveryFile(): string
    {
        return (string) config('ssrmetrics.storage.fallback.files.recovery', 'metrics/last.json');
    }
}

