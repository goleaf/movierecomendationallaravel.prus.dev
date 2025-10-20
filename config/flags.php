<?php

declare(strict_types=1);

return [
    'movie' => [
        'list_eager_load' => [
            'enabled' => env('FLAG_MOVIE_LIST_EAGER_LOAD', true),
            'relations' => [
                ['relation' => 'genres', 'limit' => (int) env('FLAG_MOVIE_LIST_EAGER_LOAD_GENRES_LIMIT', 6)],
                ['relation' => 'casts', 'limit' => (int) env('FLAG_MOVIE_LIST_EAGER_LOAD_CASTS_LIMIT', 8)],
                ['relation' => 'posters', 'limit' => (int) env('FLAG_MOVIE_LIST_EAGER_LOAD_POSTERS_LIMIT', 4)],
            ],
        ],
    ],
];
