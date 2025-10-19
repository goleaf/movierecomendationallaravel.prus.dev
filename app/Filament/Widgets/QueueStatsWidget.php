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
            Stat::make('Jobs queued', number_format($jobs)),
            Stat::make('Failed jobs', number_format($failed))->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Batches', number_format($batches)),
        ];
    }
}
