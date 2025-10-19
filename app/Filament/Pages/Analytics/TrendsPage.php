<?php

namespace App\Filament\Pages\Analytics;

use App\Support\TrendingService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

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
