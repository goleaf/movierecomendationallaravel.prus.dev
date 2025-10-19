<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CtrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.analytics.ctr';

    public static function getNavigationLabel(): string
    {
        return __('analytics.panel.navigation.ctr');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('analytics.panel.navigation_group');
    }
}
