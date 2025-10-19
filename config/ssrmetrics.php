<?php

declare(strict_types=1);

$paths = ['/', '/trends', '/analytics/ctr'];

$pathsOverride = env('SSR_METRICS_PATHS');

if (is_string($pathsOverride)) {
    $parsedPaths = array_values(array_filter(
        array_map('trim', str_getcsv($pathsOverride)),
        static fn (string $path): bool => $path !== ''
    ));

    if ($parsedPaths !== []) {
        $paths = $parsedPaths;
    }
}

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => $paths,
    'storage' => [
        'primary' => [
            'disk' => env('SSR_METRICS_STORAGE_PRIMARY_DISK', 'ssrmetrics'),
            'files' => [
                'incoming' => env('SSR_METRICS_STORAGE_PRIMARY_FILE', 'ssr-metrics.jsonl'),
                'aggregate' => env('SSR_METRICS_STORAGE_PRIMARY_AGGREGATE_FILE', 'ssr-metrics-summary.json'),
            ],
        ],
        'fallback' => [
            'disk' => env('SSR_METRICS_STORAGE_FALLBACK_DISK', 'local'),
            'files' => [
                'incoming' => env('SSR_METRICS_STORAGE_FALLBACK_FILE', 'metrics/ssr.jsonl'),
                'recovery' => env('SSR_METRICS_STORAGE_FALLBACK_RECOVERY_FILE', 'metrics/last.json'),
            ],
        ],
    ],
    'score' => [
        'weights' => [
            'speed_index' => (float) env('SSR_METRICS_WEIGHT_SPEED_INDEX', 0.35),
            'first_contentful_paint' => (float) env('SSR_METRICS_WEIGHT_FCP', 0.25),
            'largest_contentful_paint' => (float) env('SSR_METRICS_WEIGHT_LCP', 0.25),
            'time_to_interactive' => (float) env('SSR_METRICS_WEIGHT_TTI', 0.15),
        ],
        'thresholds' => [
            'passing' => (int) env('SSR_METRICS_THRESHOLD_PASSING', 80),
            'warning' => (int) env('SSR_METRICS_THRESHOLD_WARNING', 65),
        ],
    ],
    'retention' => [
        'primary_days' => (int) env('SSR_METRICS_RETENTION_PRIMARY_DAYS', 14),
        'fallback_days' => (int) env('SSR_METRICS_RETENTION_FALLBACK_DAYS', 3),
        'aggregate_days' => (int) env('SSR_METRICS_RETENTION_AGGREGATE_DAYS', 90),
    ],
    'penalties' => [
        'blocking_scripts' => [
            'per_script' => (int) env('SSR_METRICS_PENALTY_BLOCKING_PER_SCRIPT', 5),
            'max' => (int) env('SSR_METRICS_PENALTY_BLOCKING_MAX', 30),
        ],
        'missing_ldjson' => [
            'deduction' => (int) env('SSR_METRICS_PENALTY_MISSING_LDJSON', 10),
        ],
        'low_og' => [
            'minimum' => (int) env('SSR_METRICS_PENALTY_LOW_OG_MINIMUM', 3),
            'deduction' => (int) env('SSR_METRICS_PENALTY_LOW_OG_DEDUCTION', 10),
        ],
        'oversized_html' => [
            'threshold' => (int) env('SSR_METRICS_PENALTY_OVERSIZED_THRESHOLD', 900 * 1024),
            'deduction' => (int) env('SSR_METRICS_PENALTY_OVERSIZED_DEDUCTION', 20),
        ],
        'excess_images' => [
            'threshold' => (int) env('SSR_METRICS_PENALTY_EXCESS_IMAGES_THRESHOLD', 60),
            'deduction' => (int) env('SSR_METRICS_PENALTY_EXCESS_IMAGES_DEDUCTION', 10),
        ],
    ],
];
