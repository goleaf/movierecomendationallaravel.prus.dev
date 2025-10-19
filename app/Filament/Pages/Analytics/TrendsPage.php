<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class TrendsPage extends Page
{
    protected static ?string $navigationIcon='heroicon-o-chart-line-square';
    protected static string $view='filament.analytics.trends';
    protected static ?string $navigationLabel='Trends';
    protected static ?string $navigationGroup='Analytics';
}
