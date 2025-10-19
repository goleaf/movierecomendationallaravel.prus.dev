<?php

declare(strict_types=1);

$penalties = [
    'blocking_scripts' => [
        'per_script' => (int) env('SSR_METRICS_WEIGHT_BLOCKING_PER_SCRIPT', 5),
        'max' => (int) env('SSR_METRICS_WEIGHT_BLOCKING_MAX', 30),
    ],
    'missing_ldjson' => [
        'deduction' => (int) env('SSR_METRICS_WEIGHT_MISSING_LDJSON', 10),
    ],
    'low_og' => [
        'minimum' => (int) env('SSR_METRICS_WEIGHT_LOW_OG_MINIMUM', 3),
        'deduction' => (int) env('SSR_METRICS_WEIGHT_LOW_OG', 10),
    ],
    'oversized_html' => [
        'threshold' => (int) env('SSR_METRICS_WEIGHT_OVERSIZED_THRESHOLD', 900 * 1024),
        'deduction' => (int) env('SSR_METRICS_WEIGHT_OVERSIZED', 20),
    ],
    'excess_images' => [
        'threshold' => (int) env('SSR_METRICS_WEIGHT_EXCESS_IMAGES_THRESHOLD', 60),
        'deduction' => (int) env('SSR_METRICS_WEIGHT_EXCESS_IMAGES', 10),
    ],
];

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
    'weights' => [
        'score' => [
            'base' => (int) env('SSR_METRICS_WEIGHT_BASE', 100),
            'min' => (int) env('SSR_METRICS_WEIGHT_MIN', 0),
            'max' => (int) env('SSR_METRICS_WEIGHT_MAX', 100),
        ],
        'penalties' => $penalties,
        'bonuses' => [],
    ],
    'penalties' => $penalties,
];
