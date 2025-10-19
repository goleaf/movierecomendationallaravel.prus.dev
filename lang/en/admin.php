<?php

return [
    'ctr' => [
        'title' => 'CTR Analytics',
        'period' => 'Period: :from — :to',
        'line_alt' => 'CTR line chart',
        'bars_alt' => 'CTR bar chart',
        'ab_summary_heading' => 'A/B Summary',
        'ab_summary_item' => 'Variant :variant — Imps: :impressions, Clicks: :clicks, CTR: :ctr%',
        'no_data' => 'No data for the selected period.',
        'filters' => [
            'from' => 'From',
            'to' => 'To',
            'placement' => 'Placement',
            'variant' => 'Variant',
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
            'refresh' => 'Update analytics',
        ],
        'charts' => [
            'daily_heading' => 'Daily CTR (A vs B)',
            'placements_heading' => 'CTR by placement',
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
    ],
    'metrics' => [
        'title' => 'Queues / Horizon',
        'heading' => 'Queues',
        'stats' => 'Jobs: :jobs, Failed: :failed, Batches: :batches',
        'refresh' => 'Refresh stats',
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
            'view_rate' => 'View→Click %',
        ],
    ],
    'trends' => [
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
        'period' => 'Top for :days days (:from — :to)',
        'empty' => 'No items match the selected filters.',
        'clicks' => 'Clicks: :count',
        'imdb' => 'IMDb: :rating',
        'votes' => 'Votes: :count',
    ],
];
