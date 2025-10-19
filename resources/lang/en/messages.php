<?php

return [
    'app' => [
        'name' => 'MovieRec',
        'default_title' => 'MovieRec',
        'meta_description' => 'Curated picks, trends, and recommendations.',
        'og_description' => 'Curated picks and recommendations.',
        'tagline' => 'SSR • SVG charts',
        'footer' => '© :year MovieRec',
    ],
    'common' => [
        'dash' => '—',
        'clicks' => 'Clicks: :count',
        'imdb_only' => 'IMDb :rating',
        'imdb_with_votes' => 'IMDb :rating • :votes',
        'imdb_with_votes_colon' => 'IMDb: :rating • :votes',
    ],
    'home' => [
        'title' => 'Recommendations',
        'recommendations_heading' => 'Personalized recommendations',
        'recommendations_description' => 'The A/B algorithm (device cookie) selects top releases by IMDb score and freshness.',
        'empty_recommendations' => 'No recommendation data yet.',
        'trends_heading' => '7-day trends',
        'trends_description_html' => 'Recommendation clicks by placement. Learn more — <a href=":url">trends page</a>.',
        'empty_trending' => 'Click statistics have not been collected yet.',
    ],
    'trends' => [
        'title' => 'Recommendation trends',
        'heading' => 'Recommendation trends',
        'period' => 'Period: :from — :to (:days :days_short)',
        'days_short' => 'days',
        'empty' => 'No data — check click tracking or adjust the filters.',
        'votes' => ':count votes',
    ],
    'search' => [
        'title' => 'Search',
        'form' => [
            'query_placeholder' => 'Title or tt...',
            'type_label' => 'Type',
            'type_movie' => 'Movies',
            'type_series' => 'Series',
            'type_animation' => 'Animation',
            'genre_placeholder' => 'Genre',
            'year_from_placeholder' => 'Year from',
            'year_to_placeholder' => 'Year to',
            'submit' => 'Search',
        ],
        'empty' => 'Nothing found.',
    ],
    'movies' => [
        'imdb_caption' => 'IMDb :rating • :votes • Weighted :score',
        'votes' => ':count votes',
        'genres' => 'Genres: :genres',
    ],
];
