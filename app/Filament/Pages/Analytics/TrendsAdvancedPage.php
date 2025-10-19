<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class TrendsAdvancedPage extends Page
{
    protected static ?string $navigationIcon='heroicon-o-adjustments-horizontal';
    protected static string $view='filament.analytics.trends_advanced';
    protected static ?string $navigationLabel='Trends (Advanced)';
    protected static ?string $navigationGroup='Analytics';
}
