<?php

declare(strict_types=1);

$defaultPaths = ['/', '/trends', '/analytics/ctr'];

$paths = (static function (array $defaults): array {
    $normalize = static function (string $path): string {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return '';
        }

        return '/'.ltrim($trimmed, '/');
    };

    $paths = array_filter(array_map($normalize, $defaults));

    $overrideRaw = env('SSR_METRICS_PATHS');

    if (is_string($overrideRaw) && trim($overrideRaw) !== '') {
        $overridePaths = array_filter(array_map($normalize, explode(',', $overrideRaw)));

        if ($overridePaths !== []) {
            $paths = $overridePaths;
        }
    }

    $appendRaw = env('SSR_METRICS_PATHS_APPEND');

    if (is_string($appendRaw) && trim($appendRaw) !== '') {
        $paths = array_merge($paths, array_filter(array_map($normalize, explode(',', $appendRaw))));
    }

    $excludeRaw = env('SSR_METRICS_PATHS_EXCLUDE');

    if (is_string($excludeRaw) && trim($excludeRaw) !== '') {
        $exclusions = array_filter(array_map($normalize, explode(',', $excludeRaw)));

        $paths = array_values(array_filter(
            $paths,
            static fn (string $path): bool => ! in_array($path, $exclusions, true)
        ));
    }

    $unique = [];

    foreach ($paths as $path) {
        if ($path === '' || in_array($path, $unique, true)) {
            continue;
        }

        $unique[] = $path;
    }

    return $unique;
})($defaultPaths);

$defaultDisk = env('FILESYSTEM_DISK', 'local');
$primaryDisk = (string) env('SSR_METRICS_STORAGE_DISK', $defaultDisk);
$fallbackDisk = (string) env('SSR_METRICS_STORAGE_FALLBACK_DISK', $primaryDisk);

$directory = trim((string) env('SSR_METRICS_STORAGE_DIRECTORY', 'metrics'));
$directory = $directory === '' ? '' : trim(str_replace('\\', '/', $directory), '/');

$defaultJsonlPath = $directory === '' ? 'metrics/ssr.jsonl' : $directory.'/ssr.jsonl';
$jsonlPath = trim((string) env('SSR_METRICS_STORAGE_PATH', $defaultJsonlPath));
$jsonlPath = $jsonlPath === '' ? $defaultJsonlPath : str_replace('\\', '/', ltrim($jsonlPath, '/'));

$defaultLastSnapshot = $directory === '' ? 'metrics/last.json' : $directory.'/last.json';
$lastSnapshotPath = trim((string) env('SSR_METRICS_STORAGE_LAST_PATH', $defaultLastSnapshot));
$lastSnapshotPath = $lastSnapshotPath === '' ? $defaultLastSnapshot : str_replace('\\', '/', ltrim($lastSnapshotPath, '/'));

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => $paths,
    'storage' => [
        'disk' => $primaryDisk !== '' ? $primaryDisk : $defaultDisk,
        'fallback_disk' => $fallbackDisk !== '' ? $fallbackDisk : ($primaryDisk !== '' ? $primaryDisk : $defaultDisk),
        'directory' => $directory,
        'jsonl' => $jsonlPath,
        'last_snapshot' => $lastSnapshotPath,
    ],
    'retention' => [
        'database_days' => (int) env('SSR_METRICS_RETENTION_DATABASE_DAYS', 14),
        'jsonl_days' => (int) env('SSR_METRICS_RETENTION_JSONL_DAYS', 7),
    ],
    'penalties' => [
        'blocking_scripts' => [
            'per_script' => (int) env('SSR_METRICS_SCORE_BLOCKING_PER_SCRIPT', 5),
            'max' => (int) env('SSR_METRICS_SCORE_BLOCKING_MAX', 30),
        ],
        'missing_ldjson' => [
            'deduction' => (int) env('SSR_METRICS_SCORE_MISSING_LDJSON', 10),
        ],
        'low_og' => [
            'minimum' => (int) env('SSR_METRICS_SCORE_LOW_OG_MINIMUM', 3),
            'deduction' => (int) env('SSR_METRICS_SCORE_LOW_OG_DEDUCTION', 10),
        ],
        'oversized_html' => [
            'threshold' => (int) env('SSR_METRICS_SCORE_OVERSIZED_HTML_THRESHOLD', 900 * 1024),
            'deduction' => (int) env('SSR_METRICS_SCORE_OVERSIZED_HTML_DEDUCTION', 20),
        ],
        'excess_images' => [
            'threshold' => (int) env('SSR_METRICS_SCORE_EXCESS_IMAGES_THRESHOLD', 60),
            'deduction' => (int) env('SSR_METRICS_SCORE_EXCESS_IMAGES_DEDUCTION', 10),
        ],
    ],
];
