<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

class SsrMetricsService
{
    private bool $enabled;

    /**
     * @var array{include: list<string>, exclude: list<string>}
     */
    private array $paths;

    /**
     * @var array{disk: string, directory: string, database: array{table: string}, jsonl: array{file: string}}
     */
    private array $storage;

    /**
     * @var array{
     *     base: int,
     *     blocking_scripts: array{per_script: int, max_penalty: int},
     *     ldjson: array{missing_penalty: int},
     *     open_graph: array{minimum: int, penalty: int},
     *     html: array{max_kb: int, penalty: int},
     *     images: array{max_count: int, penalty: int},
     * }
     */
    private array $scoring;

    /**
     * @var array{database_days: int, jsonl_days: int}
     */
    private array $retention;

    public function __construct(Repository $config)
    {
        /** @var array<string, mixed> $raw */
        $raw = $config->get('ssrmetrics', []);

        if (! is_array($raw)) {
            throw new InvalidArgumentException('The ssrmetrics configuration must be an array.');
        }

        $this->enabled = (bool) ($raw['enabled'] ?? false);
        $this->paths = $this->validatePaths($raw['paths'] ?? []);
        $this->storage = $this->validateStorage($raw['storage'] ?? []);
        $this->scoring = $this->validateScoring($raw['scoring'] ?? []);
        $this->retention = $this->validateRetention($raw['retention'] ?? []);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function shouldTrackPath(string $path): bool
    {
        $normalisedPath = $this->normalizePath($path);

        foreach ($this->paths['exclude'] as $pattern) {
            if ($this->matchesPath($pattern, $normalisedPath)) {
                return false;
            }
        }

        if ($this->paths['include'] === []) {
            return true;
        }

        foreach ($this->paths['include'] as $pattern) {
            if ($this->matchesPath($pattern, $normalisedPath)) {
                return true;
            }
        }

        return false;
    }

    public function normalizePath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/' . ltrim($trimmed, '/');
    }

    public function storageDisk(): string
    {
        return $this->storage['disk'];
    }

    public function storageDirectory(): string
    {
        return trim($this->storage['directory'], '/');
    }

    public function databaseTable(): string
    {
        return $this->storage['database']['table'];
    }

    public function jsonlPath(): string
    {
        $directory = $this->storageDirectory();
        $file = trim($this->storage['jsonl']['file'], '/');

        if ($directory === '') {
            return $file;
        }

        if ($file === '') {
            throw new InvalidArgumentException('The SSR metrics JSONL file name cannot be empty.');
        }

        return $directory . '/' . $file;
    }

    /**
     * @return array{database_days: int, jsonl_days: int}
     */
    public function retention(): array
    {
        return $this->retention;
    }

    /**
     * @param  array{blocking_scripts?: int, ldjson_count?: int, og_count?: int, html_size?: int, img_count?: int}  $metrics
     */
    public function computeScore(array $metrics): int
    {
        $score = $this->scoring['base'];

        $blockingScripts = max(0, (int) ($metrics['blocking_scripts'] ?? 0));
        $blockingPenalty = min(
            $this->scoring['blocking_scripts']['max_penalty'],
            $blockingScripts * $this->scoring['blocking_scripts']['per_script']
        );

        $score -= $blockingPenalty;

        if (($metrics['ldjson_count'] ?? 0) <= 0) {
            $score -= $this->scoring['ldjson']['missing_penalty'];
        }

        if (($metrics['og_count'] ?? 0) < $this->scoring['open_graph']['minimum']) {
            $score -= $this->scoring['open_graph']['penalty'];
        }

        $htmlSize = max(0, (int) ($metrics['html_size'] ?? 0));
        $maxHtmlBytes = $this->scoring['html']['max_kb'] * 1024;

        if ($maxHtmlBytes > 0 && $htmlSize > $maxHtmlBytes) {
            $score -= $this->scoring['html']['penalty'];
        }

        if (($metrics['img_count'] ?? 0) > $this->scoring['images']['max_count']) {
            $score -= $this->scoring['images']['penalty'];
        }

        return max(0, $score);
    }

    /**
     * @param  array{include?: mixed, exclude?: mixed}  $paths
     * @return array{include: list<string>, exclude: list<string>}
     */
    private function validatePaths(array $paths): array
    {
        $include = $paths['include'] ?? [];
        $exclude = $paths['exclude'] ?? [];

        if (! is_array($include) || ! is_array($exclude)) {
            throw new InvalidArgumentException('The ssrmetrics.paths configuration must contain include and exclude arrays.');
        }

        $normalise = function (array $patterns): array {
            $normalised = [];

            foreach ($patterns as $pattern) {
                if (! is_string($pattern)) {
                    throw new InvalidArgumentException('Path patterns must be strings.');
                }

                $pattern = trim($pattern);

                if ($pattern === '') {
                    continue;
                }

                if ($pattern !== '*' && $pattern !== '/') {
                    $pattern = '/' . ltrim($pattern, '/');
                } else {
                    $pattern = $pattern === '/' ? '/' : '*';
                }

                $normalised[] = $pattern;
            }

            return array_values(array_unique($normalised));
        };

        return [
            'include' => $normalise($include),
            'exclude' => $normalise($exclude),
        ];
    }

    /**
     * @param  array{disk?: mixed, directory?: mixed, database?: mixed, jsonl?: mixed}  $storage
     * @return array{disk: string, directory: string, database: array{table: string}, jsonl: array{file: string}}
     */
    private function validateStorage(array $storage): array
    {
        $disk = $storage['disk'] ?? '';
        $directory = $storage['directory'] ?? '';
        $database = $storage['database'] ?? [];
        $jsonl = $storage['jsonl'] ?? [];

        if (! is_string($disk) || trim($disk) === '') {
            throw new InvalidArgumentException('The ssrmetrics.storage.disk configuration must be a non-empty string.');
        }

        if (! is_string($directory)) {
            throw new InvalidArgumentException('The ssrmetrics.storage.directory configuration must be a string.');
        }

        if (! is_array($database) || ! is_string($database['table'] ?? null) || trim((string) $database['table']) === '') {
            throw new InvalidArgumentException('The ssrmetrics.storage.database.table configuration must be a non-empty string.');
        }

        if (! is_array($jsonl) || ! is_string($jsonl['file'] ?? null)) {
            throw new InvalidArgumentException('The ssrmetrics.storage.jsonl.file configuration must be a string.');
        }

        $file = trim((string) $jsonl['file']);

        if ($file === '') {
            throw new InvalidArgumentException('The ssrmetrics.storage.jsonl.file configuration cannot be empty.');
        }

        if (str_contains($file, '/')) {
            throw new InvalidArgumentException('The ssrmetrics.storage.jsonl.file configuration must not contain directory separators; configure ssrmetrics.storage.directory instead.');
        }

        return [
            'disk' => $disk,
            'directory' => $directory,
            'database' => ['table' => (string) $database['table']],
            'jsonl' => ['file' => $file],
        ];
    }

    /**
     * @param  array{base?: mixed, blocking_scripts?: mixed, ldjson?: mixed, open_graph?: mixed, html?: mixed, images?: mixed}  $scoring
     * @return array{
     *     base: int,
     *     blocking_scripts: array{per_script: int, max_penalty: int},
     *     ldjson: array{missing_penalty: int},
     *     open_graph: array{minimum: int, penalty: int},
     *     html: array{max_kb: int, penalty: int},
     *     images: array{max_count: int, penalty: int},
     * }
     */
    private function validateScoring(array $scoring): array
    {
        $base = $scoring['base'] ?? 100;
        $blocking = $scoring['blocking_scripts'] ?? [];
        $ldjson = $scoring['ldjson'] ?? [];
        $og = $scoring['open_graph'] ?? [];
        $html = $scoring['html'] ?? [];
        $images = $scoring['images'] ?? [];

        foreach ([
            'base' => $base,
            'blocking_scripts.per_script' => $blocking['per_script'] ?? null,
            'blocking_scripts.max_penalty' => $blocking['max_penalty'] ?? null,
            'ldjson.missing_penalty' => $ldjson['missing_penalty'] ?? null,
            'open_graph.minimum' => $og['minimum'] ?? null,
            'open_graph.penalty' => $og['penalty'] ?? null,
            'html.max_kb' => $html['max_kb'] ?? null,
            'html.penalty' => $html['penalty'] ?? null,
            'images.max_count' => $images['max_count'] ?? null,
            'images.penalty' => $images['penalty'] ?? null,
        ] as $key => $value) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException(sprintf('The ssrmetrics.scoring.%s configuration must be numeric.', $key));
            }
        }

        return [
            'base' => (int) $base,
            'blocking_scripts' => [
                'per_script' => max(0, (int) $blocking['per_script']),
                'max_penalty' => max(0, (int) $blocking['max_penalty']),
            ],
            'ldjson' => [
                'missing_penalty' => max(0, (int) $ldjson['missing_penalty']),
            ],
            'open_graph' => [
                'minimum' => max(0, (int) $og['minimum']),
                'penalty' => max(0, (int) $og['penalty']),
            ],
            'html' => [
                'max_kb' => max(0, (int) $html['max_kb']),
                'penalty' => max(0, (int) $html['penalty']),
            ],
            'images' => [
                'max_count' => max(0, (int) $images['max_count']),
                'penalty' => max(0, (int) $images['penalty']),
            ],
        ];
    }

    /**
     * @param  array{database_days?: mixed, jsonl_days?: mixed}  $retention
     * @return array{database_days: int, jsonl_days: int}
     */
    private function validateRetention(array $retention): array
    {
        $database = $retention['database_days'] ?? 30;
        $jsonl = $retention['jsonl_days'] ?? 14;

        foreach ([
            'database_days' => $database,
            'jsonl_days' => $jsonl,
        ] as $key => $value) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException(sprintf('The ssrmetrics.retention.%s configuration must be numeric.', $key));
            }
        }

        return [
            'database_days' => max(0, (int) $database),
            'jsonl_days' => max(0, (int) $jsonl),
        ];
    }

    private function matchesPath(string $pattern, string $path): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $path) {
            return true;
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return str_starts_with($path, rtrim($prefix, '/'));
        }

        return false;
    }
}
