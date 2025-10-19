<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SsrMetricsStorage
{
    /**
     * @return array<int, string>
     */
    public static function disks(): array
    {
        $default = (string) config('filesystems.default', 'local');
        $primary = (string) config('ssrmetrics.storage.disk', $default);
        $fallback = (string) config('ssrmetrics.storage.fallback_disk', $primary);

        $candidates = [$primary, $fallback, $default];
        $unique = [];

        foreach ($candidates as $disk) {
            if ($disk === '') {
                continue;
            }

            if (! in_array($disk, $unique, true)) {
                $unique[] = $disk;
            }
        }

        if ($unique === []) {
            $unique[] = 'local';
        }

        return $unique;
    }

    public static function jsonlPath(): string
    {
        $path = (string) config('ssrmetrics.storage.jsonl', 'metrics/ssr.jsonl');

        return self::normalizePath($path === '' ? 'metrics/ssr.jsonl' : $path);
    }

    public static function lastSnapshotPath(): string
    {
        $path = (string) config('ssrmetrics.storage.last_snapshot', 'metrics/last.json');

        return self::normalizePath($path === '' ? 'metrics/last.json' : $path);
    }

    public static function directory(): string
    {
        $directory = (string) config('ssrmetrics.storage.directory', 'metrics');
        $directory = trim(str_replace('\\', '/', $directory), '/');

        return $directory;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readJsonl(): array
    {
        $path = self::jsonlPath();

        foreach (self::disks() as $disk) {
            $storage = self::attemptDisk($disk);

            if ($storage === null || ! $storage->exists($path)) {
                continue;
            }

            $content = trim((string) $storage->get($path));

            if ($content === '') {
                continue;
            }

            $records = [];

            foreach (preg_split("/\r?\n/", $content) ?: [] as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    $records[] = $decoded;
                }
            }

            if ($records !== []) {
                return $records;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readLastSnapshot(): array
    {
        $path = self::lastSnapshotPath();

        foreach (self::disks() as $disk) {
            $storage = self::attemptDisk($disk);

            if ($storage === null || ! $storage->exists($path)) {
                continue;
            }

            $decoded = json_decode((string) $storage->get($path), true);

            if (! is_array($decoded)) {
                continue;
            }

            if (array_is_list($decoded)) {
                $records = [];

                foreach ($decoded as $record) {
                    if (is_array($record)) {
                        $records[] = $record;
                    }
                }

                if ($records !== []) {
                    return $records;
                }

                continue;
            }

            return [$decoded];
        }

        return [];
    }

    private static function normalizePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        $normalized = ltrim($normalized, '/');

        return $normalized === '' ? 'metrics/ssr.jsonl' : $normalized;
    }

    private static function attemptDisk(string $disk): ?FilesystemAdapter
    {
        try {
            /** @var FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            return $storage;
        } catch (Throwable) {
            return null;
        }
    }
}

