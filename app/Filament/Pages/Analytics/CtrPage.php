<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class CtrPage extends Page
{
    protected static ?string $navigationIcon='heroicon-o-chart-bar';
    protected static string $view='filament.analytics.ctr';
    protected static ?string $navigationLabel='CTR';
    protected static ?string $navigationGroup='Analytics';
}
