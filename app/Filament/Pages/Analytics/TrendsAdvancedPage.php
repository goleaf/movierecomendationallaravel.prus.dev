<?php

namespace App\Filament\Pages\Analytics;

use BackedEnum;
use UnitEnum;

class TrendsAdvancedPage extends TrendsPage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected string $view = 'filament.analytics.trends_advanced';

    protected static ?string $navigationLabel = 'Trends (Advanced)';

    protected static UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $slug = 'trends-advanced';

    public bool $showAdvancedFilters = true;

    public function mount(): void
    {
        parent::mount();
    }
}
