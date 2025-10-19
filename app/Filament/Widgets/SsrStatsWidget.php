<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(SsrMetricsService::class)->summary();

        $paths = (int) $summary['today_paths'];
        $delta = (float) $summary['delta'];
        $trendDescription = __('analytics.widgets.ssr_stats.trend', [
            'delta' => number_format($delta, 2),
            'rolling' => number_format((float) $summary['rolling'], 2),
        ]);

        $description = trans_choice(
            'analytics.widgets.ssr_stats.description',
            $paths,
            ['count' => number_format($paths)]
        );

        return [
            Stat::make(__('analytics.widgets.ssr_stats.today'), number_format((float) $summary['today'], 2))
                ->description($trendDescription)
                ->descriptionIcon($delta >= 0 ? 'heroicon-s-arrow-trending-up' : 'heroicon-s-arrow-trending-down')
                ->color($delta >= 0 ? 'success' : 'danger'),
            Stat::make(__('analytics.widgets.ssr_stats.yesterday'), number_format((float) $summary['yesterday'], 2))
                ->description(__('analytics.widgets.ssr_stats.yesterday_description')),
            Stat::make(__('analytics.widgets.ssr_stats.paths'), number_format($paths))
                ->description($description),
        ];
    }
}
