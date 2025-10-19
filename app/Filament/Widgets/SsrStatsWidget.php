<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $headline = app(SsrAnalyticsService::class)->headline();

        return [
            Stat::make($headline['label'], (string) $headline['score'])
                ->description($headline['description']),
        ];
    }
}
