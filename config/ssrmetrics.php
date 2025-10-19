<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),

    'paths' => [
        '/',
        '/trends',
        '/analytics/ctr',
    ],

    'storage' => [
        'disk' => env('SSR_METRICS_DISK', env('FILESYSTEM_DISK', 'local')),
        'jsonl' => 'metrics/ssr.jsonl',
        'snapshot' => 'metrics/ssr_snapshot.json',
    ],

    'limits' => [
        'snapshot' => 50,
        'trend_days' => 30,
        'issue_window_days' => 2,
        'jsonl_records' => 240,
    ],

    'penalties' => [
        'blocking_script' => 5,
        'blocking_cap' => 30,
        'missing_ldjson' => 10,
        'missing_og' => 10,
        'og_threshold' => 3,
        'oversized_bytes' => 900 * 1024,
        'oversized_penalty' => 20,
        'image_threshold' => 60,
        'image_penalty' => 10,
    ],
];
