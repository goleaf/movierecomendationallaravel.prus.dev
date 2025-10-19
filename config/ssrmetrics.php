<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),

    'paths' => ['/', '/trends', '/analytics/ctr'],

    'storage' => env('SSR_METRICS_STORAGE', 'database'),

    'retention_days' => (int) env('SSR_METRICS_RETENTION_DAYS', 30),

    'jsonl' => [
        'disk' => env('SSR_METRICS_JSONL_DISK', 'local'),
        'path' => env('SSR_METRICS_JSONL_PATH', 'metrics/ssr.jsonl'),
    ],

    'penalty_weights' => [
        'timeout' => (int) env('SSR_METRICS_PENALTY_TIMEOUT', 25),
        'error' => (int) env('SSR_METRICS_PENALTY_ERROR', 50),
        'slow_first_byte' => (int) env('SSR_METRICS_PENALTY_SLOW_FIRST_BYTE', 10),
        'missing_json_ld' => (int) env('SSR_METRICS_PENALTY_MISSING_JSON_LD', 5),
        'missing_open_graph' => (int) env('SSR_METRICS_PENALTY_MISSING_OPEN_GRAPH', 5),
        'blocking_scripts' => (int) env('SSR_METRICS_PENALTY_BLOCKING_SCRIPTS', 3),
        'heavy_html' => (int) env('SSR_METRICS_PENALTY_HEAVY_HTML', 2),
    ],
];
