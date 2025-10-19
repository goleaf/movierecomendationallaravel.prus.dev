<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\SsrMetricsStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreSsrMetric implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    private array $normalizedPayload = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $this->normalizedPayload = $this->normalizePayload($this->payload);

        if ($this->storeInDatabase()) {
            return;
        }

        $this->storeInJsonl();
    }

    private function storeInDatabase(): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $data = [
                'path' => $this->normalizedPayload['path'],
                'score' => $this->normalizedPayload['score'],
                'created_at' => $this->normalizedPayload['collected_at']->toDateTimeString(),
                'updated_at' => $this->normalizedPayload['collected_at']->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $this->normalizedPayload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $this->normalizedPayload['collected_at']->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'size') && $this->normalizedPayload['html_bytes'] !== null) {
                $data['size'] = $this->normalizedPayload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'html_bytes') && $this->normalizedPayload['html_bytes'] !== null) {
                $data['html_bytes'] = $this->normalizedPayload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count') && $this->normalizedPayload['meta_count'] !== null) {
                $data['meta_count'] = $this->normalizedPayload['meta_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count') && $this->normalizedPayload['og_count'] !== null) {
                $data['og_count'] = $this->normalizedPayload['og_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count') && $this->normalizedPayload['ldjson_count'] !== null) {
                $data['ldjson_count'] = $this->normalizedPayload['ldjson_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count') && $this->normalizedPayload['img_count'] !== null) {
                $data['img_count'] = $this->normalizedPayload['img_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts') && $this->normalizedPayload['blocking_scripts'] !== null) {
                $data['blocking_scripts'] = $this->normalizedPayload['blocking_scripts'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $data['has_json_ld'] = $this->normalizedPayload['has_json_ld'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $data['has_open_graph'] = $this->normalizedPayload['has_open_graph'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta')) {
                $data['meta'] = json_encode($this->normalizedPayload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    private function storeInJsonl(): void
    {
        $path = SsrMetricsStorage::jsonlPath();
        $directory = SsrMetricsStorage::directory();
        $disks = SsrMetricsStorage::disks();

        $payload = [
            'ts' => $this->normalizedPayload['collected_at']->toIso8601String(),
            'path' => $this->normalizedPayload['path'],
            'score' => $this->normalizedPayload['score'],
                'html_bytes' => $this->normalizedPayload['html_bytes'],
                'size' => $this->normalizedPayload['html_bytes'],
                'html_size' => $this->normalizedPayload['html_bytes'],
                'meta' => $this->normalizedPayload['meta'],
                'meta_count' => $this->normalizedPayload['meta_count'],
                'og_count' => $this->normalizedPayload['og_count'],
                'og' => $this->normalizedPayload['og_count'],
                'ldjson_count' => $this->normalizedPayload['ldjson_count'],
                'ld' => $this->normalizedPayload['ldjson_count'],
                'img_count' => $this->normalizedPayload['img_count'],
                'imgs' => $this->normalizedPayload['img_count'],
                'blocking_scripts' => $this->normalizedPayload['blocking_scripts'],
                'blocking' => $this->normalizedPayload['blocking_scripts'],
                'first_byte_ms' => $this->normalizedPayload['first_byte_ms'],
            'has_json_ld' => $this->normalizedPayload['has_json_ld'],
            'has_open_graph' => $this->normalizedPayload['has_open_graph'],
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::error('Failed encoding SSR metric payload for storage.', [
                'exception' => $e,
                'payload' => $this->payload,
            ]);

            return;
        }

        $attemptedDisks = [];
        $lastException = null;

        foreach ($disks as $disk) {
            try {
                /** @var FilesystemAdapter $storage */
                $storage = Storage::disk($disk);

                if ($directory !== '') {
                    $this->ensureDirectoryExists($storage, $directory);
                }

                $storage->append($path, $encoded);

                return;
            } catch (Throwable $e) {
                $attemptedDisks[] = $disk;
                $lastException = $e;
            }
        }

        Log::error('Failed storing SSR metric.', [
            'exception' => $lastException,
            'payload' => $this->payload,
            'disks' => $attemptedDisks,
            'path' => $path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     path: string,
     *     score: int,
     *     html_bytes: int|null,
     *     meta_count: int|null,
     *     og_count: int|null,
     *     ldjson_count: int|null,
     *     img_count: int|null,
     *     blocking_scripts: int|null,
     *     first_byte_ms: int,
     *     has_json_ld: bool,
     *     has_open_graph: bool,
     *     meta: array<string, mixed>,
     *     collected_at: Carbon,
     * }
     */
    private function normalizePayload(array $payload): array
    {
        $path = isset($payload['path']) ? (string) $payload['path'] : '/';
        $path = '/'.ltrim($path, '/');

        $score = (int) ($payload['score'] ?? 0);
        $score = max(0, min(100, $score));

        $htmlBytes = $this->extractInteger($payload, ['html_bytes', 'html_size', 'size']);
        $metaCount = $this->extractInteger($payload, ['meta_count']);
        $ogCount = $this->extractInteger($payload, ['og_count']);
        $ldjsonCount = $this->extractInteger($payload, ['ldjson_count']);
        $imgCount = $this->extractInteger($payload, ['img_count']);
        $blockingScripts = $this->extractInteger($payload, ['blocking_scripts']);
        $firstByteMs = $this->extractInteger($payload, ['first_byte_ms']) ?? 0;

        $hasJsonLd = array_key_exists('has_json_ld', $payload)
            ? (bool) $payload['has_json_ld']
            : (($ldjsonCount ?? 0) > 0);

        $hasOpenGraph = array_key_exists('has_open_graph', $payload)
            ? (bool) $payload['has_open_graph']
            : (($ogCount ?? 0) > 0);

        $meta = $payload['meta'] ?? [];
        $meta = is_array($meta) ? $meta : [];
        $meta = array_merge($meta, [
            'first_byte_ms' => $firstByteMs,
            'html_bytes' => $htmlBytes,
            'html_size' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'has_json_ld' => $hasJsonLd,
            'has_open_graph' => $hasOpenGraph,
        ]);

        $collectedAtSource = $payload['collected_at']
            ?? $payload['timestamp']
            ?? $payload['ts']
            ?? null;

        return [
            'path' => $path,
            'score' => $score,
            'html_bytes' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'first_byte_ms' => $firstByteMs,
            'has_json_ld' => $hasJsonLd,
            'has_open_graph' => $hasOpenGraph,
            'meta' => $meta,
            'collected_at' => $this->resolveTimestamp($collectedAtSource),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function extractInteger(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null) {
                return null;
            }

            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        return null;
    }

    private function resolveTimestamp(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::createFromInterface($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                // Fall through to now().
            }
        }

        return Carbon::now();
    }

    private function ensureDirectoryExists(FilesystemAdapter $storage, string $directory): void
    {
        if ($directory === '') {
            return;
        }

        if (! $storage->directoryExists($directory)) {
            $storage->makeDirectory($directory);
        }
    }
}
