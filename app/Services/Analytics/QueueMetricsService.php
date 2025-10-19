<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class QueueMetricsService
{
    /**
     * @return array{
     *     jobs: int,
     *     failed: int,
     *     batches: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null},
     * }
     */
    public function snapshot(): array
    {
        $jobs = Schema::hasTable('jobs') ? (int) (DB::table('jobs')->count() ?? 0) : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) (DB::table('failed_jobs')->count() ?? 0) : 0;
        $batches = Schema::hasTable('job_batches') ? (int) (DB::table('job_batches')->count() ?? 0) : 0;

        $horizon = [
            'workload' => null,
            'supervisors' => null,
        ];

        try {
            $workload = Redis::hgetall('horizon:workload');
            $supervisors = Redis::smembers('horizon:supervisors');

            if (! empty($workload)) {
                $horizon['workload'] = $workload;
            }

            if (! empty($supervisors)) {
                $horizon['supervisors'] = $supervisors;
            }
        } catch (\Throwable) {
            // Horizon might not be configured locally.
        }

        return [
            'jobs' => $jobs,
            'failed' => $failed,
            'batches' => $batches,
            'horizon' => $horizon,
        ];
    }

    /**
     * @return array{
     *     queue: int,
     *     failed: int,
     *     processed: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null},
     * }
     */
    public function getMetrics(): array
    {
        $snapshot = $this->snapshot();

        return [
            'queue' => $snapshot['jobs'],
            'failed' => $snapshot['failed'],
            'processed' => $snapshot['batches'],
            'horizon' => $snapshot['horizon'],
        ];
    }
}
