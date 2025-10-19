<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

class TrendsAdvancedPage extends TrendsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.analytics.trends_advanced';

    protected static ?string $navigationLabel = 'Trends (Advanced)';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'trends-advanced';

    public bool $showAdvancedFilters = true;

    public function mount(): void
    {
        parent::mount();
    }
}
