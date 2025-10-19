<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
    'storage' => [
        'driver' => env('SSR_METRICS_DRIVER', 'database'),
        'fallback_driver' => env('SSR_METRICS_FALLBACK_DRIVER', 'jsonl'),
        'retention_days' => (int) env('SSR_METRICS_RETENTION_DAYS', 30),
        'database' => [
            'table' => 'ssr_metrics',
        ],
        'jsonl' => [
            'disk' => env('SSR_METRICS_DISK', env('FILESYSTEM_DISK', 'local')),
            'path' => env('SSR_METRICS_JSONL_PATH', 'metrics/ssr.jsonl'),
        ],
    ],
];
