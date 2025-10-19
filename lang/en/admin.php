<?php

declare(strict_types=1);

return [
    'analytics_tabs' => [
        'heading' => 'Analytics overview',
        'queue' => [
            'label' => 'Queues',
        ],
        'ctr' => [
            'label' => 'CTR trends',
        ],
        'funnels' => [
            'label' => 'Funnels',
        ],
        'ssr' => [
            'label' => 'SSR metrics',
        ],
        'experiments' => [
            'label' => 'Experiments',
        ],
    ],
    'ctr' => [
        'title' => 'CTR Analytics',
        'period' => 'Period: :from — :to',
        'line_alt' => 'CTR line chart',
        'bars_alt' => 'CTR bar chart',
        'ab_summary_heading' => 'A/B Summary',
        'ab_summary_item' => 'Variant :variant — Imps: :impressions, Clicks: :clicks, CTR: :ctr%',
        'no_data' => 'No data for the selected period.',
        'noscript_notice' => 'Enable JavaScript to view the charts. Key metrics remain available in the tables below.',
        'filters' => [
            'aria_label' => 'CTR analytics filters',
            'from' => 'From',
            'to' => 'To',
            'placement' => 'Placement',
            'variant' => 'Variant',
            'variant_all' => 'All variants',
            'placement_all' => 'All placements',
            'apply' => 'Update analytics',
            'placements' => [
                'all' => 'All placements',
                'home' => 'Home page',
                'show' => 'Show page',
                'trends' => 'Trends page',
            ],
            'variants' => [
                'all' => 'All variants',
                'a' => 'Variant A',
                'b' => 'Variant B',
            ],
        ],
        'charts' => [
            'daily_heading' => 'Daily CTR (A vs B)',
            'daily_description' => 'Line chart comparing daily CTR for variants A and B.',
            'placements_heading' => 'CTR by placement',
            'placements_description' => 'Bar chart showing CTR split by placement for variants A and B.',
        ],
        'placement_clicks' => [
            'heading' => 'Clicks by placement',
            'placement' => 'Placement',
            'clicks' => 'Clicks',
        ],
        'funnels' => [
            'heading' => 'Funnels',
            'total' => 'Total',
        ],
        'empty_summary' => 'No summary data for the selected filters.',
    ],
    'metrics' => [
        'title' => 'Queues / Horizon',
        'heading' => 'Queues',
        'stats' => 'Jobs: :jobs, Failed: :failed, Batches: :batches',
        'refresh' => 'Refresh stats',
        'queue_label' => 'Jobs queued',
        'failed_label' => 'Failed jobs',
        'processed_label' => 'Processed batches',
        'labels' => [
            'jobs' => 'Jobs queued',
            'failed' => 'Failed jobs',
            'batches' => 'Batches',
        ],
        'horizon' => [
            'heading' => 'Horizon',
            'workload' => 'Workload',
            'supervisors' => 'Supervisors',
            'empty' => 'No Horizon metrics available.',
            'actions' => [
                'pause' => [
                    'label' => 'Pause Horizon',
                    'confirm' => 'Pause all Horizon workers?',
                    'success' => 'Horizon queues paused.',
                ],
                'resume' => [
                    'label' => 'Resume Horizon',
                    'confirm' => 'Resume Horizon workers?',
                    'success' => 'Horizon queues resumed.',
                ],
                'failed' => 'Unable to update Horizon queue status.',
                'unauthorized' => 'You do not have permission to manage Horizon queues.',
                'unavailable' => 'Horizon is not installed or reachable.',
            ],
        ],
        'horizon_workload' => 'Horizon workload',
        'horizon_supervisors' => 'Horizon supervisors',
        'horizon_empty' => 'No Horizon metrics available.',
    ],
    'ssr' => [
        'title' => 'SSR Analytics',
        'headline' => [
            'heading' => 'SSR Score',
        ],
        'trend' => [
            'heading' => 'SSR score trend',
            'empty' => 'No SSR trend data available.',
            'aria_label' => 'Line chart showing how the SSR score changes over time.',
            'range' => '{0}No data|{1}Last :days day|[2,*]Last :days days',
        ],
        'drop' => [
            'heading' => 'Top pages by SSR score drop',
            'empty' => 'No SSR drops detected for the selected period.',
            'columns' => [
                'path' => 'Path',
                'yesterday' => 'Yesterday',
                'today' => 'Today',
                'delta' => 'Δ',
            ],
        ],
    ],
    'funnel' => [
        'period' => 'Period: :from — :to',
        'headers' => [
            'placement' => 'Placement',
            'imps' => 'Imps',
            'clicks' => 'Clicks',
            'views' => 'Views',
            'ctr' => 'CTR %',
            'cuped_ctr' => 'CTR (CUPED) %',
            'view_rate' => 'View→Click %',
        ],
    ],
    'trends' => [
        'days_label' => 'Days',
        'filters' => [
            'days' => 'Days',
            'type' => 'Type',
            'genre' => 'Genre',
            'year_from' => 'Year from',
            'year_to' => 'Year to',
        ],
        'days_option' => ':days days',
        'type_placeholder' => 'Type',
        'types' => [
            'movie' => 'Movies',
            'series' => 'Series',
            'animation' => 'Animation',
        ],
        'genre_placeholder' => 'Genre',
        'year_from_placeholder' => 'Year from',
        'year_to_placeholder' => 'Year to',
        'apply' => 'Show',
        'period' => 'Period: :from — :to (:days days)',
        'empty' => 'No trending titles found for the selected filters.',
        'clicks' => 'Clicks: :count',
        'imdb' => 'IMDb: :rating',
        'votes' => 'Votes: :count',
    ],
];
