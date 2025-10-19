<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS_ENABLED', env('SSR_METRICS', false)),

    'paths' => [
        'include' => array_values(array_filter(array_map(
            static fn (string $path): string => '/' . ltrim($path, '/'),
            array_map('trim', explode(',', (string) env('SSR_METRICS_PATHS', '/,/trends,/analytics/ctr')))
        ))),
        'exclude' => array_values(array_filter(array_map(
            static fn (string $path): string => '/' . ltrim($path, '/'),
            array_map('trim', explode(',', (string) env('SSR_METRICS_EXCLUDE_PATHS', '')))
        ))),
    ],

    'storage' => [
        'disk' => env('SSR_METRICS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => env('SSR_METRICS_STORAGE_DIRECTORY', 'metrics'),
        'database' => [
            'table' => env('SSR_METRICS_DATABASE_TABLE', 'ssr_metrics'),
        ],
        'jsonl' => [
            'file' => env('SSR_METRICS_JSONL_FILE', 'ssr.jsonl'),
        ],
    ],

    'scoring' => [
        'base' => env('SSR_METRICS_BASE_SCORE', 100),
        'blocking_scripts' => [
            'per_script' => env('SSR_METRICS_BLOCKING_PENALTY', 5),
            'max_penalty' => env('SSR_METRICS_BLOCKING_MAX', 30),
        ],
        'ldjson' => [
            'missing_penalty' => env('SSR_METRICS_LDJSON_PENALTY', 10),
        ],
        'open_graph' => [
            'minimum' => env('SSR_METRICS_OG_MIN', 3),
            'penalty' => env('SSR_METRICS_OG_PENALTY', 10),
        ],
        'html' => [
            'max_kb' => env('SSR_METRICS_HTML_MAX_KB', 900),
            'penalty' => env('SSR_METRICS_HTML_PENALTY', 20),
        ],
        'images' => [
            'max_count' => env('SSR_METRICS_IMG_MAX', 60),
            'penalty' => env('SSR_METRICS_IMG_PENALTY', 10),
        ],
    ],

    'retention' => [
        'database_days' => env('SSR_METRICS_RETENTION_DATABASE_DAYS', 30),
        'jsonl_days' => env('SSR_METRICS_RETENTION_JSONL_DAYS', 14),
    ],
];
