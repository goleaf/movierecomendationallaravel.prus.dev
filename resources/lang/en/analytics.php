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
            'ssr' => 'SSR',
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
                'cuped_ctr' => 'CTR (CUPED) %',
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
            'guard_rails' => [
                'minimum_samples' => 'Need at least :min impressions per variant to evaluate significance.',
            ],
        ],
        'ssr_stats' => [
            'label' => 'SSR Score',
            'paths' => '{0}No paths|{1}:count path|[2,*]:count paths',
            'samples' => '{0}No samples|{1}:count sample|[2,*]:count samples',
            'first_byte' => 'First byte: :value ms',
            'delta' => [
                'score' => 'Δ score: :value',
                'first_byte' => 'Δ first byte: :value ms',
                'paths' => 'Δ paths: :value',
                'samples' => 'Δ samples: :value',
            ],
            'periods' => [
                'today' => [
                    'label' => 'Today',
                ],
                'yesterday' => [
                    'label' => 'Yesterday',
                ],
                'seven_days' => [
                    'label' => 'Last 7 days',
                    'range' => ':from — :to',
                ],
            ],
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
            'heading' => 'SSR score trend (daily vs 7-day average)',
            'datasets' => [
                'daily' => 'Daily average score',
                'rolling' => '7-day rolling average',
            ],
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
