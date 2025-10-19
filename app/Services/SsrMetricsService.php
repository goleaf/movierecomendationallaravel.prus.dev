<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SsrMetricsService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): void
    {
        $driver = (string) config('ssrmetrics.storage.driver', 'auto');
        $drivers = $this->normalizeDrivers($driver);

        foreach ($drivers as $target) {
            $stored = match ($target) {
                'database' => $this->storeInDatabase($payload),
                'jsonl' => $this->storeInJsonl($payload),
                default => false,
            };

            if ($stored) {
                $this->enforceRetention($target);

                return;
            }
        }

        Log::warning('SSR metric payload could not be stored using configured drivers.', [
            'driver' => $driver,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeDrivers(string $driver): array
    {
        return match ($driver) {
            'database' => ['database'],
            'jsonl' => ['jsonl'],
            default => ['database', 'jsonl'],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInDatabase(array $payload): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $data = [
                'path' => $payload['path'],
                'score' => $payload['score'],
                'created_at' => now(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'size') && isset($payload['html_size'])) {
                $data['size'] = $payload['html_size'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count') && isset($payload['meta_count'])) {
                $data['meta_count'] = $payload['meta_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count') && isset($payload['og_count'])) {
                $data['og_count'] = $payload['og_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count') && isset($payload['ldjson_count'])) {
                $data['ldjson_count'] = $payload['ldjson_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count') && isset($payload['img_count'])) {
                $data['img_count'] = $payload['img_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts') && isset($payload['blocking_scripts'])) {
                $data['blocking_scripts'] = $payload['blocking_scripts'];
            }

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms') && isset($payload['first_byte_ms'])) {
                $data['first_byte_ms'] = $payload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta') && isset($payload['meta'])) {
                $data['meta'] = json_encode($payload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to next driver.', [
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
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $record = [
                'ts' => now()->toIso8601String(),
                'path' => $payload['path'],
                'score' => $payload['score'],
                'size' => $payload['html_size'] ?? null,
                'html_size' => $payload['html_size'] ?? null,
                'meta' => $payload['meta'] ?? null,
                'meta_count' => $payload['meta_count'] ?? null,
                'og' => $payload['og_count'] ?? null,
                'ld' => $payload['ldjson_count'] ?? null,
                'imgs' => $payload['img_count'] ?? null,
                'blocking' => $payload['blocking_scripts'] ?? null,
                'first_byte_ms' => $payload['first_byte_ms'] ?? null,
                'has_json_ld' => ($payload['ldjson_count'] ?? 0) > 0,
                'has_open_graph' => ($payload['og_count'] ?? 0) > 0,
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($record, JSON_THROW_ON_ERROR));

            return true;
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric in JSONL.', [
                'exception' => $e,
                'payload' => $payload,
            ]);

            return false;
        }
    }

    private function enforceRetention(string $driver): void
    {
        $days = (int) config('ssrmetrics.storage.retention_days', 0);

        if ($days <= 0) {
            return;
        }

        if ($driver === 'database' && Schema::hasTable('ssr_metrics')) {
            DB::table('ssr_metrics')
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
        }

        if ($driver === 'jsonl' && Storage::exists('metrics/ssr.jsonl')) {
            $cutoff = now()->subDays($days);
            $lines = preg_split('/\r?\n/', Storage::get('metrics/ssr.jsonl')) ?: [];
            $filtered = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $timestamp = isset($decoded['ts']) ? Carbon::make($decoded['ts']) : null;

                if ($timestamp === null || $timestamp->lt($cutoff)) {
                    continue;
                }

                $filtered[] = $line;
            }

            $contents = implode(PHP_EOL, $filtered);

            if ($contents !== '') {
                $contents .= PHP_EOL;
            }

            Storage::put('metrics/ssr.jsonl', $contents);
        }
    }
}
