<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
    'penalties' => [
        'blocking_scripts' => [
            'per_script' => 5,
            'max' => 30,
        ],
        'missing_ldjson' => [
            'deduction' => 10,
        ],
        'low_og' => [
            'minimum' => 3,
            'deduction' => 10,
        ],
        'oversized_html' => [
            'threshold' => 900 * 1024,
            'deduction' => 20,
        ],
        'excess_images' => [
            'threshold' => 60,
            'deduction' => 10,
        ],
    ],
    'storage' => [
        'order' => ['database', 'jsonl'],
        'database' => [
            'enabled' => env('SSR_METRICS_DB_ENABLED', true),
            'retention_days' => env('SSR_METRICS_DB_RETENTION_DAYS', 30),
        ],
        'jsonl' => [
            'enabled' => env('SSR_METRICS_JSONL_ENABLED', true),
            'disk' => env('SSR_METRICS_JSONL_DISK', 'local'),
            'path' => env('SSR_METRICS_JSONL_PATH', 'metrics/ssr.jsonl'),
            'retention_days' => env('SSR_METRICS_JSONL_RETENTION_DAYS', 14),
        ],
    ],
];
