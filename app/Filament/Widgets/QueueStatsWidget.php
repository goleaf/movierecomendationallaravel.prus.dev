<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $jobs = Schema::hasTable('jobs') ? (int) (DB::table('jobs')->count() ?? 0) : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) (DB::table('failed_jobs')->count() ?? 0) : 0;
        $batches = Schema::hasTable('job_batches') ? (int) (DB::table('job_batches')->count() ?? 0) : 0;

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
