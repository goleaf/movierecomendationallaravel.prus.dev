<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
    'storage' => [
        'driver' => env('SSR_METRICS_DRIVER', 'auto'),
        'retention_days' => (int) env('SSR_METRICS_RETENTION_DAYS', 30),
    ],
    'scoring' => [
        'weights' => [
            'blocking_scripts' => (int) env('SSR_METRICS_WEIGHT_BLOCKING', 5),
            'ldjson_missing' => (int) env('SSR_METRICS_WEIGHT_LDJSON', 10),
            'opengraph_insufficient' => (int) env('SSR_METRICS_WEIGHT_OPENGRAPH', 10),
            'oversized_html' => (int) env('SSR_METRICS_WEIGHT_HTML_SIZE', 20),
            'image_overflow' => (int) env('SSR_METRICS_WEIGHT_IMAGES', 10),
            'blocking_cap' => (int) env('SSR_METRICS_WEIGHT_BLOCKING_CAP', 30),
        ],
        'thresholds' => [
            'opengraph_minimum' => (int) env('SSR_METRICS_OPENGRAPH_MINIMUM', 3),
            'max_html_bytes' => (int) env('SSR_METRICS_MAX_HTML_BYTES', 900 * 1024),
            'max_images' => (int) env('SSR_METRICS_MAX_IMAGES', 60),
        ],
    ],
];
