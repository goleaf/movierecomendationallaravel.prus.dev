<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class TrendsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-line-square';

    protected static string $view = 'filament.analytics.trends';

    public static function getNavigationLabel(): string
    {
        return __('analytics.panel.navigation.trends');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('analytics.panel.navigation_group');
    }
}
