<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\QueueMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QueueStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $snapshot = app(QueueMetricsService::class)->snapshot();

        $jobs = $snapshot['jobs'];
        $failed = $snapshot['failed'];
        $batches = $snapshot['batches'];

        return [
            Stat::make(__('analytics.widgets.queue_stats.jobs.label'), number_format($jobs))
                ->description(__('analytics.widgets.queue_stats.jobs.description', ['count' => number_format($jobs)])),
            Stat::make(__('analytics.widgets.queue_stats.failed.label'), number_format($failed))
                ->description(__('analytics.widgets.queue_stats.failed.description', ['count' => number_format($failed)]))
                ->color($failed > 0 ? 'danger' : 'success'),
            Stat::make(__('analytics.widgets.queue_stats.batches.label'), number_format($batches))
                ->description(__('analytics.widgets.queue_stats.batches.description', ['count' => number_format($batches)])),
        ];
    }
}
