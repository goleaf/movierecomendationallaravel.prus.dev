<?php

declare(strict_types=1);

return [

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
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'timeout' => env('TMDB_HTTP_TIMEOUT', 20),
        'rate_limit' => [
            'max_attempts' => env('TMDB_RATE_LIMIT_MAX_ATTEMPTS', 30),
            'decay_seconds' => env('TMDB_RATE_LIMIT_DECAY_SECONDS', 10),
        ],
        'backoff' => [
            'max_retries' => env('TMDB_BACKOFF_RETRIES', 3),
            'initial_ms' => env('TMDB_BACKOFF_INITIAL_MS', 500),
            'multiplier' => env('TMDB_BACKOFF_MULTIPLIER', 2.0),
            'max_ms' => env('TMDB_BACKOFF_MAX_MS', 8000),
        ],
    ],

    'omdb' => [
        'key' => env('OMDB_API_KEY'),
        'base_url' => env('OMDB_BASE_URL', 'https://www.omdbapi.com'),
        'timeout' => env('OMDB_HTTP_TIMEOUT', 15),
        'rate_limit' => [
            'max_attempts' => env('OMDB_RATE_LIMIT_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('OMDB_RATE_LIMIT_DECAY_SECONDS', 1),
        ],
        'backoff' => [
            'max_retries' => env('OMDB_BACKOFF_RETRIES', 3),
            'initial_ms' => env('OMDB_BACKOFF_INITIAL_MS', 500),
            'multiplier' => env('OMDB_BACKOFF_MULTIPLIER', 2.0),
            'max_ms' => env('OMDB_BACKOFF_MAX_MS', 5000),
        ],
    ],

];
