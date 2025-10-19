<?php

return [
    'ctr' => [
        'title' => 'CTR Analytics',
        'period' => 'Period: :from — :to',
        'line_alt' => 'CTR line chart',
        'bars_alt' => 'CTR bar chart',
        'ab_summary_heading' => 'A/B Summary',
        'ab_summary_item' => 'Variant :variant — Imps: :impressions, Clicks: :clicks, CTR: :ctr%',
    ],
    'metrics' => [
        'title' => 'Queues / Horizon',
        'heading' => 'Queues',
        'stats' => 'Jobs: :jobs, Failed: :failed, Batches: :batches',
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
    ],
];
