<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class TrendsAdvancedPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.analytics.trends_advanced';

    public static function getNavigationLabel(): string
    {
        return __('analytics.panel.navigation.trends_advanced');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('analytics.panel.navigation_group');
    }
}
