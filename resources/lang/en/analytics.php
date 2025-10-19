<?php

declare(strict_types=1);

return [
    'panel' => [
        'brand' => 'Analytics',
        'navigation_group' => 'Analytics',
        'navigation' => [
            'ctr' => 'CTR',
            'trends' => 'Trends',
            'queue' => 'Queue / Horizon',
        ],
    ],
    'widgets' => [
        'funnel' => [
            'heading' => 'Funnels (7 days)',
            'period' => 'Period: :from — :to',
            'columns' => [
                'placement' => 'Placement',
                'imps' => 'Imps',
                'clicks' => 'Clicks',
                'views' => 'Views',
                'ctr' => 'CTR %',
                'view_rate' => 'View→Click %',
            ],
            'placements' => [
                'home' => 'home',
                'show' => 'show',
                'trends' => 'trends',
            ],
            'total' => 'Total',
        ],
        'queue_stats' => [
            'jobs' => [
                'label' => 'Jobs queued',
                'description' => 'Queued jobs: :count',
            ],
            'failed' => [
                'label' => 'Failed jobs',
                'description' => 'Failed jobs: :count',
            ],
            'batches' => [
                'label' => 'Batches',
                'description' => 'Job batches: :count',
            ],
        ],
        'z_test' => [
            'ctr_a' => 'CTR A',
            'ctr_b' => 'CTR B',
            'z_test' => 'Z-test',
            'impressions' => 'Imps: :count',
            'clicks' => 'Clicks: :count',
            'description_format' => ':impressions · :clicks',
            'p_value' => [
                'significant' => 'p < 0.05',
                'not_significant' => 'p ≥ 0.05',
            ],
        ],
        'ssr_stats' => [
            'label' => 'SSR Score',
            'description' => '{0}No tracked paths|{1}:count path|[2,*]:count paths',
        ],
        'ssr_drop' => [
            'heading' => 'Top pages by SSR score drop (day over day)',
            'columns' => [
                'path' => 'Path',
                'yesterday' => 'Yesterday',
                'today' => 'Today',
                'delta' => 'Δ',
            ],
        ],
        'ssr_score' => [
            'heading' => 'SSR Score (trend)',
            'dataset' => 'SSR score',
        ],
        'images' => [
            'ctr_line_alt' => 'CTR line chart',
            'ctr_bars_alt' => 'CTR bar chart',
        ],
    ],
    'svg' => [
        'ctr_line_title' => 'Daily CTR: A (blue) vs B (green)',
        'ctr_bars_title' => 'CTR by placement (A — blue, B — green)',
    ],
    'hints' => [
        'ssr' => [
            'add_defer' => 'Add the defer attribute to blocking scripts',
            'add_json_ld' => 'Add JSON-LD',
            'expand_og' => 'Expand OG tags',
            'reduce_payload' => 'Reduce HTML or image payload',
            'missing_json_ld' => 'No JSON-LD present',
            'add_og' => 'Add OG tags',
        ],
    ],
];
