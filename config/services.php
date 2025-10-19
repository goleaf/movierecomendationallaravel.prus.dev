<?php

declare(strict_types=1);

return [

    'artwork' => [
        'proxy_url' => env('ARTWORK_PROXY_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3/'),
        'cache_ttl' => (int) env('TMDB_CACHE_TTL', 3600),
        'timeout' => (float) env('TMDB_TIMEOUT', 10),
        'retry' => [
            'attempts' => (int) env('TMDB_RETRY_ATTEMPTS', 2),
            'delay_ms' => (int) env('TMDB_RETRY_DELAY_MS', 250),
        ],
        'backoff' => [
            'multiplier' => (float) env('TMDB_BACKOFF_MULTIPLIER', 2.0),
            'max_delay_ms' => (int) env('TMDB_BACKOFF_MAX_DELAY_MS', 2000),
        ],
        'rate_limit' => [
            'window' => (int) env('TMDB_RATE_LIMIT_WINDOW', 10),
            'allowance' => (int) env('TMDB_RATE_LIMIT_ALLOWANCE', 35),
        ],
        'accepted_locales' => array_values(array_filter(array_map(
            static fn (string $locale): string => trim($locale),
            explode(',', (string) env('TMDB_ACCEPTED_LOCALES', 'en-US,pt-BR')),
        ))),
        'default_locale' => env('TMDB_DEFAULT_LOCALE', 'en-US'),
    ],

    'omdb' => [
        'key' => env('OMDB_API_KEY'),
        'base_url' => env('OMDB_BASE_URL', 'https://www.omdbapi.com/'),
        'timeout' => (float) env('OMDB_TIMEOUT', 10),
        'retry' => [
            'attempts' => (int) env('OMDB_RETRY_ATTEMPTS', 1),
            'delay_ms' => (int) env('OMDB_RETRY_DELAY_MS', 300),
        ],
        'backoff' => [
            'multiplier' => (float) env('OMDB_BACKOFF_MULTIPLIER', 2.0),
            'max_delay_ms' => (int) env('OMDB_BACKOFF_MAX_DELAY_MS', 1200),
        ],
        'rate_limit' => [
            'window' => (int) env('OMDB_RATE_LIMIT_WINDOW', 1),
            'allowance' => (int) env('OMDB_RATE_LIMIT_ALLOWANCE', 5),
        ],
        'default_params' => [
            'r' => env('OMDB_DEFAULT_FORMAT', 'json'),
            'plot' => env('OMDB_DEFAULT_PLOT', 'short'),
        ],
    ],

];
