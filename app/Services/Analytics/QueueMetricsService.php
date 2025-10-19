<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMetricsService
{
    /**
     * @return array{
     *     queue: int,
     *     failed: int,
     *     processed: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null}
     * }
     */
    public function getMetrics(): array
    {
        $queueCount = (int) DB::table('jobs')->count();
        $failed = (int) DB::table('failed_jobs')->count();
        $processed = (int) DB::table('job_batches')->count();

        $horizon = ['workload' => null, 'supervisors' => null];

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
            // Ignore Redis connection issues for this panel.
        }

        return [
            'queue' => $queueCount,
            'failed' => $failed,
            'processed' => $processed,
            'horizon' => $horizon,
        ];
    }
}
