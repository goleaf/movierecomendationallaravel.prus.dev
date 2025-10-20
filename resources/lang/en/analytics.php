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
            'impressions' => 'Imps::count',
            'clicks' => 'Clicks::count',
            'description_format' => ':impressions :clicks',
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
            'empty' => 'No SSR metrics available.',
            'summary' => '{0}No tracked paths between :start and :end.|{1}Tracking :paths path across :samples samples between :start and :end.|[2,*]Tracking :paths paths across :samples samples between :start and :end.',
            'periods' => [
                'today' => [
                    'label' => 'Today',
                    'comparison' => 'vs yesterday',
                ],
                'yesterday' => [
                    'label' => 'Yesterday',
                    'comparison' => 'vs prior day',
                ],
                'seven_days' => [
                    'label' => 'Last 7 days',
                    'comparison' => 'vs previous week',
                ],
                'delta' => 'Δ :delta :comparison',
                'delta_unavailable' => 'Δ n/a',
                'samples' => '{0}No samples|{1}:count sample|[2,*]:count samples',
                'first_byte' => [
                    'label' => 'First byte: :value ms',
                    'delta' => '(:delta ms :comparison)',
                ],
                'first_byte_unavailable' => 'First byte not recorded',
                'range' => 'Range: :start → :end',
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
