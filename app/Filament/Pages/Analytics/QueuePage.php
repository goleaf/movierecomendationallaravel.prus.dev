<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueuePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.analytics.queue';

    protected static ?string $navigationLabel = 'Queue / Horizon';

    protected static ?string $navigationGroup = 'Analytics';

    public int $queueCount = 0;

    public int $failedCount = 0;

    public int $processedCount = 0;

    /**
     * @var array{workload: array<string, int>, supervisors: array<int, string>}
     */
    public array $horizon = [
        'workload' => [],
        'supervisors' => [],
    ];

    public function mount(): void
    {
        $this->loadMetrics();
    }

    public function refreshMetrics(): void
    {
        $this->loadMetrics();
    }

    protected function loadMetrics(): void
    {
        $this->queueCount = (int) (DB::table('jobs')->count() ?? 0);
        $this->failedCount = (int) (DB::table('failed_jobs')->count() ?? 0);
        $this->processedCount = (int) (DB::table('job_batches')->count() ?? 0);

        $this->horizon = [
            'workload' => [],
            'supervisors' => [],
        ];

        try {
            $workload = Redis::hgetall('horizon:workload');
            $supervisors = Redis::smembers('horizon:supervisors');

            if (is_array($workload) && $workload !== []) {
                $this->horizon['workload'] = array_map('intval', $workload);
            }

            if (is_array($supervisors) && $supervisors !== []) {
                $this->horizon['supervisors'] = array_values($supervisors);
            }
        } catch (\Throwable) {
            $this->horizon = [
                'workload' => [],
                'supervisors' => [],
            ];
        }
    }
}
