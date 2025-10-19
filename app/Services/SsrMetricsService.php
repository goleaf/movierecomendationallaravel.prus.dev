<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class SsrMetricsService
{
    private string $driver;

    private ?string $fallbackDriver;

    private int $retentionDays;

    private string $databaseTable;

    private string $jsonlDisk;

    private string $jsonlPath;

    public function __construct()
    {
        $config = config('ssrmetrics.storage');

        $this->driver = (string) ($config['driver'] ?? 'database');
        $fallbackDriver = $config['fallback_driver'] ?? null;
        $this->fallbackDriver = is_string($fallbackDriver) && $fallbackDriver !== ''
            ? $fallbackDriver
            : null;
        $this->retentionDays = (int) ($config['retention_days'] ?? 30);
        $this->databaseTable = (string) ($config['database']['table'] ?? 'ssr_metrics');

        $jsonlConfig = $config['jsonl'] ?? [];
        $this->jsonlDisk = (string) ($jsonlConfig['disk'] ?? config('filesystems.default', 'local'));
        $this->jsonlPath = (string) ($jsonlConfig['path'] ?? 'metrics/ssr.jsonl');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): bool
    {
        $drivers = $this->uniqueDrivers();

        foreach ($drivers as $driver) {
            if ($this->attemptStore($driver, $payload)) {
                return true;
            }
        }

        Log::error('Failed storing SSR metric. All configured drivers failed.', [
            'drivers' => $drivers,
        ]);

        return false;
    }

    public function prune(): void
    {
        if ($this->retentionDays <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subDays($this->retentionDays);

        foreach ($this->uniqueDrivers() as $driver) {
            if ($driver === 'database') {
                $this->pruneDatabase($cutoff);
            }

            if ($driver === 'jsonl') {
                $this->pruneJsonl($cutoff);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attemptStore(string $driver, array $payload): bool
    {
        return match ($driver) {
            'database' => $this->storeInDatabase($payload),
            'jsonl' => $this->storeInJsonl($payload),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInDatabase(array $payload): bool
    {
        if (! Schema::hasTable($this->databaseTable)) {
            return false;
        }

        try {
            $data = [
                'path' => $payload['path'],
                'score' => $payload['score'],
                'created_at' => $payload['collected_at']->toDateTimeString(),
                'updated_at' => $payload['collected_at']->toDateTimeString(),
            ];

            if (Schema::hasColumn($this->databaseTable, 'first_byte_ms')) {
                $data['first_byte_ms'] = $payload['first_byte_ms'];
            }

            if (Schema::hasColumn($this->databaseTable, 'collected_at')) {
                $data['collected_at'] = $payload['collected_at']->toDateTimeString();
            }

            if (Schema::hasColumn($this->databaseTable, 'size') && $payload['html_bytes'] !== null) {
                $data['size'] = $payload['html_bytes'];
            }

            if (Schema::hasColumn($this->databaseTable, 'html_bytes') && $payload['html_bytes'] !== null) {
                $data['html_bytes'] = $payload['html_bytes'];
            }

            if (Schema::hasColumn($this->databaseTable, 'meta_count') && $payload['meta_count'] !== null) {
                $data['meta_count'] = $payload['meta_count'];
            }

            if (Schema::hasColumn($this->databaseTable, 'og_count') && $payload['og_count'] !== null) {
                $data['og_count'] = $payload['og_count'];
            }

            if (Schema::hasColumn($this->databaseTable, 'ldjson_count') && $payload['ldjson_count'] !== null) {
                $data['ldjson_count'] = $payload['ldjson_count'];
            }

            if (Schema::hasColumn($this->databaseTable, 'img_count') && $payload['img_count'] !== null) {
                $data['img_count'] = $payload['img_count'];
            }

            if (Schema::hasColumn($this->databaseTable, 'blocking_scripts') && $payload['blocking_scripts'] !== null) {
                $data['blocking_scripts'] = $payload['blocking_scripts'];
            }

            if (Schema::hasColumn($this->databaseTable, 'has_json_ld')) {
                $data['has_json_ld'] = $payload['has_json_ld'];
            }

            if (Schema::hasColumn($this->databaseTable, 'has_open_graph')) {
                $data['has_open_graph'] = $payload['has_open_graph'];
            }

            if (Schema::hasColumn($this->databaseTable, 'meta')) {
                $data['meta'] = json_encode($payload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table($this->databaseTable)->insert($data);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInJsonl(array $payload): bool
    {
        try {
            $disk = Storage::disk($this->jsonlDisk);

            $directory = trim((string) dirname($this->jsonlPath), '.');
            if ($directory !== '' && ! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $record = [
                'ts' => $payload['collected_at']->toIso8601String(),
                'path' => $payload['path'],
                'score' => $payload['score'],
                'html_bytes' => $payload['html_bytes'],
                'size' => $payload['html_bytes'],
                'html_size' => $payload['html_bytes'],
                'meta' => $payload['meta'],
                'meta_count' => $payload['meta_count'],
                'og_count' => $payload['og_count'],
                'og' => $payload['og_count'],
                'ldjson_count' => $payload['ldjson_count'],
                'ld' => $payload['ldjson_count'],
                'img_count' => $payload['img_count'],
                'imgs' => $payload['img_count'],
                'blocking_scripts' => $payload['blocking_scripts'],
                'blocking' => $payload['blocking_scripts'],
                'first_byte_ms' => $payload['first_byte_ms'],
                'has_json_ld' => $payload['has_json_ld'],
                'has_open_graph' => $payload['has_open_graph'],
            ];

            $disk->append($this->jsonlPath, json_encode($record, JSON_THROW_ON_ERROR));

            return true;
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric in JSONL.', [
                'exception' => $e,
                'path' => $this->jsonlPath,
            ]);

            return false;
        }
    }

    private function pruneDatabase(Carbon $cutoff): void
    {
        if (! Schema::hasTable($this->databaseTable)) {
            return;
        }

        if (Schema::hasColumn($this->databaseTable, 'collected_at')) {
            DB::table($this->databaseTable)
                ->where('collected_at', '<', $cutoff)
                ->delete();

            return;
        }

        if (Schema::hasColumn($this->databaseTable, 'created_at')) {
            DB::table($this->databaseTable)
                ->where('created_at', '<', $cutoff)
                ->delete();
        }
    }

    private function pruneJsonl(Carbon $cutoff): void
    {
        $disk = Storage::disk($this->jsonlDisk);

        if (! $disk->exists($this->jsonlPath)) {
            return;
        }

        $contents = $disk->get($this->jsonlPath);
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $retained = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $retained[] = $line;

                continue;
            }

            $timestamp = $decoded['ts'] ?? null;

            if (! is_string($timestamp) || $timestamp === '') {
                $retained[] = json_encode($decoded, JSON_THROW_ON_ERROR);

                continue;
            }

            try {
                $entryTime = Carbon::parse($timestamp);
            } catch (Throwable) {
                $retained[] = json_encode($decoded, JSON_THROW_ON_ERROR);

                continue;
            }

            if ($entryTime->greaterThanOrEqualTo($cutoff)) {
                $retained[] = json_encode($decoded, JSON_THROW_ON_ERROR);
            }
        }

        if ($retained === []) {
            $disk->put($this->jsonlPath, '');

            return;
        }

        $disk->put($this->jsonlPath, implode(PHP_EOL, $retained).PHP_EOL);
    }

    /**
     * @return array<int, string>
     */
    private function uniqueDrivers(): array
    {
        $drivers = [$this->driver];

        if ($this->fallbackDriver !== null) {
            $drivers[] = $this->fallbackDriver;
        }

        return array_values(array_unique(array_filter($drivers, static fn ($driver): bool => is_string($driver) && $driver !== '')));
    }
}
