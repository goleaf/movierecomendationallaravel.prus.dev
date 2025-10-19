<?php

return [
    'publish_resource' => false,

    'group' => 'Administration',

    'navigation_sort' => 90,

    'navigation_icon' => 'heroicon-o-user',

    'impersonate' => [
        'enabled' => false,
        'banner' => [
            'render_hook' => env('FILAMENT_IMPERSONATE_BANNER_RENDER_HOOK', 'panels::body.start'),
            'style' => env('FILAMENT_IMPERSONATE_BANNER_STYLE', 'dark'),
            'fixed' => env('FILAMENT_IMPERSONATE_BANNER_FIXED', true),
            'position' => env('FILAMENT_IMPERSONATE_BANNER_POSITION', 'top'),
            'styles' => [
                'light' => [
                    'text' => '#1f2937',
                    'background' => '#f3f4f6',
                    'border' => '#e8eaec',
                ],
                'dark' => [
                    'text' => '#f3f4f6',
                    'background' => '#1f2937',
                    'border' => '#374151',
                ],
            ],
        ],
        'redirect_to' => '/analytics',
        'back_to' => '/analytics',
        'leave_middleware' => 'auth',
        'auth_guard' => 'web',
    ],

    'shield' => false,

    'simple' => false,

    'teams' => false,

    'styled_columns' => false,

    'model' => \App\Models\User::class,

    'team_model' => \App\Models\Team::class,

    'roles_model' => \Spatie\Permission\Models\Role::class,

    'resource' => [
        'table' => [
            'class' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Tables\UsersTable::class,
            'filters' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Tables\UserFilters::class,
            'actions' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Tables\UserActions::class,
            'bulkActions' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Tables\UserBulkActions::class,
        ],
        'form' => [
            'class' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Schemas\UserForm::class,
        ],
        'infolist' => [
            'class' => \TomatoPHP\FilamentUsers\Filament\Resources\Users\Schemas\UserInfolist::class,
        ],
    ],

    'avatar_collection' => 'avatar',
];
