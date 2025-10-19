<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
    'weights' => [
        'blocking_scripts' => [
            'penalty' => 5,
            'max_penalty' => 30,
        ],
        'missing_ldjson' => 10,
        'minimum_open_graph_tags' => 3,
        'missing_open_graph' => 10,
        'large_html_threshold' => 900 * 1024,
        'large_html_penalty' => 20,
        'image_threshold' => 60,
        'image_penalty' => 10,
    ],
];
