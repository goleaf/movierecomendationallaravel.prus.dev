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
            Stat::make('Jobs queued', number_format($jobs)),
            Stat::make('Failed jobs', number_format($failed))->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Batches', number_format($batches)),
        ];
    }
}
