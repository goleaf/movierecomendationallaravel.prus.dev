<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueuePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.analytics.queue';

    public static function getNavigationLabel(): string
    {
        return __('analytics.panel.navigation.queue');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('analytics.panel.navigation_group');
    }
}
