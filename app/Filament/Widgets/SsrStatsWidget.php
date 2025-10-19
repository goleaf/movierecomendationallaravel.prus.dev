<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    private SsrMetricsService $metrics;

    public function boot(SsrMetricsService $metrics): void
    {
        $this->metrics = $metrics;
    }

    protected function getStats(): array
    {
        $summary = $this->metrics->getLatestScoreSummary();
        $score = $summary['score'];
        $paths = $summary['paths'];

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
