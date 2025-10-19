<?php

namespace App\Filament\Pages\Analytics;

use App\Support\TrendingService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

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
