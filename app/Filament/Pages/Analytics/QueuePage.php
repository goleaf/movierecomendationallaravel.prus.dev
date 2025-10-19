<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class QueuePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.analytics.queue';

    protected static ?string $navigationLabel = 'Queue / Horizon';

    protected static ?string $navigationGroup = 'Analytics';

    public int $queueCount = 0;

    public int $failedCount = 0;

    public int $batchCount = 0;

    /**
     * @var array<string,mixed>
     */
    public array $horizon = [
        'workload' => [],
        'supervisors' => [],
    ];

    public function mount(): void
    {
        $this->refreshMetrics();
    }

    public function refreshMetrics(): void
    {
        $this->queueCount = $this->countTable('jobs');
        $this->failedCount = $this->countTable('failed_jobs');
        $this->batchCount = $this->countTable('job_batches');

        $this->horizon = [
            'workload' => [],
            'supervisors' => [],
        ];

        try {
            $workload = Redis::hgetall('horizon:workload');
            if (! empty($workload)) {
                $this->horizon['workload'] = $workload;
            }

            $supervisors = Redis::smembers('horizon:supervisors');
            if (! empty($supervisors)) {
                $this->horizon['supervisors'] = $supervisors;
            }
        } catch (\Throwable $exception) {
            $this->horizon['error'] = $exception->getMessage();
        }
    }

    protected function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }
}
