<?php

declare(strict_types=1);

return [
    'list_relations' => [
        'enabled' => env('MOVIE_LIST_RELATIONS_ENABLED', true),
        'relations' => [
            'casts' => [
                'relation' => 'castMembers',
                'limit' => 4,
            ],
            'posters' => [
                'limit' => 3,
            ],
        ],
    ],
];
