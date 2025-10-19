<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;

class QueuePage extends Page
{
    protected static ?string $navigationIcon='heroicon-o-queue-list';
    protected static string $view='filament.analytics.queue';
    protected static ?string $navigationLabel='Queue / Horizon';
    protected static ?string $navigationGroup='Analytics';
}
