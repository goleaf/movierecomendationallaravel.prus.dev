<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    private SsrMetricsService $ssrMetricsService;

    public function boot(SsrMetricsService $ssrMetricsService): void
    {
        $this->ssrMetricsService = $ssrMetricsService;
    }

    protected function getStats(): array
    {
        $summary = ($this->ssrMetricsService ??= app(SsrMetricsService::class))->latestScoreSummary();
        $score = $summary['score'];
        $paths = $summary['path_count'];

        $description = trans_choice(
            'analytics.widgets.ssr_stats.description',
            $paths,
            ['count' => number_format($paths)]
        );

        return [
            Stat::make(__('analytics.widgets.ssr_stats.label'), (string) $score)
                ->description($description),
        ];
    }
}
