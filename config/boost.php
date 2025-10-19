<?php

declare(strict_types=1);

use Laravel\Boost\Mcp\Tools\DatabaseQuery;
use Laravel\Boost\Mcp\Tools\DatabaseSchema;
use Laravel\Boost\Mcp\Tools\GetAbsoluteUrl;
use Laravel\Boost\Mcp\Tools\GetConfig;
use Laravel\Boost\Mcp\Tools\ListArtisanCommands;
use Laravel\Boost\Mcp\Tools\ListRoutes;
use Laravel\Boost\Mcp\Tools\SearchDocs;
use Laravel\Boost\Mcp\Tools\Tinker;

return [
    /*
    |--------------------------------------------------------------------------
    | Boost Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Boost functionality - which
    | will prevent Boost's routes from being registered and will also
    | disable Boost's browser logging functionality from operating.
    |
    */
    'enabled' => env('BOOST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Boost Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | The following option may be used to enable or disable the browser logs
    | watcher feature within Laravel Boost. The log watcher will read any
    | errors within the browser's console to give Boost better context.
    */
    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', true),

    /*
    |--------------------------------------------------------------------------
    | Hosted API overrides
    |--------------------------------------------------------------------------
    |
    | The hosted API powers documentation search and other remote Boost
    | capabilities. Override the base URL when self-hosting Boost services.
    */
    'hosted' => [
        'api_url' => env('BOOST_HOSTED_API_URL', 'https://boost.laravel.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP configuration
    |--------------------------------------------------------------------------
    |
    | Define the MCP tooling exposed to AI agents. Capabilities map the
    | business workflows we rely on (analytics & Filament) to the underlying
    | Boost MCP tools. The include / exclude lists allow us to keep the tool
    | surface area focused on read-heavy helpers that are safe to run in
    | development.
    */
    'mcp' => [
        'capabilities' => [
            'artisan' => [
                'tool' => ListArtisanCommands::class,
                'description' => 'List available Artisan commands, including project-specific wrappers for analytics pipelines and Filament scaffolding.',
            ],
            'docs' => [
                'tool' => SearchDocs::class,
                'description' => 'Query Laravel-hosted documentation scoped to the packages and versions installed in this project.',
            ],
            'config' => [
                'tool' => GetConfig::class,
                'description' => 'Inspect configuration toggles that gate analytics dashboards and recommendation experiments.',
            ],
            'routes' => [
                'tool' => ListRoutes::class,
                'description' => 'Review route definitions used by Filament panels and analytics feeds.',
            ],
            'urls' => [
                'tool' => GetAbsoluteUrl::class,
                'description' => 'Generate absolute URLs for previewing Filament resources and analytics visualisations.',
            ],
            'schema' => [
                'tool' => DatabaseSchema::class,
                'description' => 'Read-only export of database schema details supporting analytics queries.',
            ],
        ],
        'tools' => [
            'include' => [],
            'exclude' => [
                DatabaseQuery::class,
                Tinker::class,
            ],
        ],
        'resources' => [
            'include' => [],
            'exclude' => [],
        ],
        'prompts' => [
            'include' => [],
            'exclude' => [],
        ],
    ],
];
